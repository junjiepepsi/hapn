<?php
namespace hapn\swoole;

use hapn\curl\Request;
use hapn\Exception;
use hapn\util\Logger;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/9/23 15:49
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Application.php
 */
class Application
{
    const STATUS_OK = 'ok';
    const STATUS_ERROR = 'error';
    const STATUS_EXCEPTION = 'exception';
    const STATUS_NOTFOUND = 'notfound';

    private $currentRes;

    private $server;
    private $settings = [];
    private $permissionIp = [];//白名单
    private $bootstrap;


    /**
     * Application constructor.
     * @param string $host
     * @param int $port
     * @throws Exception
     */
    public function __construct(string $host = '0.0.0.0', int $port = 10020)
    {
        ini_set('display_errors', 1);
        error_reporting(E_ALL |E_STRICT);

        self::log('swoole version:' . swoole_version());
        $this->initSetting($host, $port);

        foreach (['NS_ROOT', 'CONF_ROOT'] as $key) {
            if (!defined($key)) {
                throw new Exception($key . ' not defined');
            }
        }
    }

    /**
     * 删除启动的脚本
     * @return mixed
     */
    private function killBootstrap()
    {
        $cmd = "ps aux|grep '{$this->bootstrap}'|grep -v grep|awk '{print $2}'|xargs kill > /dev/null 2>&1";
        exec($cmd, $outputs, $code);
        return $code;
    }

    /**
     * 开始运行
     */
    public function run()
    {
        if ($this->bootstrap) {
            if (!$this->killBootstrap()) {
                sleep(1);
            }
        }

        $this->server = new \swoole_http_server($this->settings['host'], $this->settings['port']);
        self::log("start server at {$this->settings['host']}:{$this->settings['port']}");

        $this->server->set($this->settings);

        //回调函数
        $call = [
            'start',
            'workerStart',
            'managerStart',
            'request',
            'task',
            'finish',
            'workerStop',
            'shutdown',
        ];
        //事件回调函数绑定
        foreach ($call as $v) {
            $m = 'on' . ucfirst($v);
            if (method_exists($this, $m)) {
                $this->server->on($v, [$this, $m]);
            }
        }

        // 设置全局错误处理函数
        set_error_handler(array($this, 'errorHandler'));

        // 启动一个脚本
        // TODO 最好在结束本Application的时候将bootstrap校本kill掉
        if ($this->bootstrap) {
            system('nohup '.$this->bootstrap.' > /dev/null 2>1 &');
        }

        $this->server->start();
    }

    /**
     * 启动时候执行的方法
     */
    public function onStart()
    {
        // 设置进程名称
        cli_set_process_title("terminal_master_" . $this->settings['port']);
    }

    /**
     * 初始化设置
     * @param $host
     * @param $port
     */
    private function initSetting($host, $port)
    {

        $confs = [
            'host' => $host,
            'port' => $port,
            'worker_num' => 4,
            'task_worker_num' => 8,
        ];
        if (defined('CONF_ROOT')) {
            if (is_file(CONF_ROOT . '/server.ini')) {
                $_confs = parse_ini_file(CONF_ROOT . '/server.ini');
                foreach ($_confs['server'] as $k => $v) {
                    $confs[$k] = $v;
                }
                $this->permissionIp = $_confs['permission_ip'];
                $this->bootstrap = $_confs['bootstrap'] ?? '';
            }
        }
        $this->settings = $confs;
        self::log($confs);
    }

    /**
     * 调用方法
     * @param        $uri
     * @param string $type
     * @return array [
     *   \ReflectionClass,
     * \ReflectionMethod
     * ]
     * @throws Exception
     */
    private function callMethod($uri, $type = 'controller')
    {
        $info = parse_url($uri);

        // 检测路径是否只包含允许的字符
        $path = trim($info['path']);
        if (!preg_match('#^\/?([a-z][0-9a-z\-\_\/]+)?$#', $path)) {
            throw new Exception('terminal.pathIllegal');
        }
        $arr = array_diff(explode('/', trim($path, '/')), ['']);
        // 检测路径是否多于两段（用/分割）
        if (count($arr) < 2) {
            throw new Exception('terminal.pathError');
        }
        $method = array_pop($arr);

        $clsName = ucfirst(array_pop($arr));
        $clsPath = rtrim(implode("\\", $arr), "\\") . $clsName;
        $clsName = rtrim(NS_ROOT, "\\") . "\\{$type}\\" . $clsPath;

        if (!class_exists($clsName, true)) {
            throw new Exception('terminal.classNotFound');
        }

        $refClass = new \ReflectionClass($clsName);
        if (!$refClass->hasMethod($method)) {
            throw new Exception('terminal.methodNotFound');
        }
        $refMethod = $refClass->getMethod($method);

        return [$refClass, $refMethod];
    }


    /**
     * 执行任务时的回调函数
     * @param $serv
     * @param $taskId
     * @param $fromId
     * @param $data
     * @return string
     */
    public function onTask($serv, $taskId, $fromId, $data)
    {
        $taskData = json_decode($data, true);
        if ($taskData === false) {
            return json_encode([self::STATUS_ERROR, 'terminal.dataError', ''], JSON_UNESCAPED_UNICODE);
        }
        list($name, $params, $callback) = $taskData;

        self::log("New AsyncTask[id=$taskId]");

        try {
            list($refClass, $refMethod) = $this->callMethod($name, 'task');

            $args = $this->getMethodArgs($refMethod, $params);

            $ctl = $refClass->newInstance($taskId);
            $ctl->uri = $name;
            $ctl->log('task start');
            $ret = $refMethod->invokeArgs($ctl, $args);
            $ctl->log('task end');

            $this->sendCallback($callback, $taskId, self::STATUS_OK, $ret);
        } catch (Exception $ex) {
            $message = $ex->getMessage();
            $status = self::STATUS_EXCEPTION;
            switch ($message) {
                case 'terminal.classNotFound':
                case 'terminal.methodNotFound':
                    $status = self::STATUS_NOTFOUND;
                    break;
                    break;
            }
            $this->sendCallback(
                $callback,
                $taskId,
                $status,
                [
                    'msg' => $ex->getMessage(),
                    'trace' => $ex->getTraceAsString(),
                ]
            );
        } catch (\Exception $ex) {
            $this->sendCallback(
                $callback,
                $taskId,
                self::STATUS_EXCEPTION,
                [
                    'msg' => $ex->getMessage(),
                    'trace' => $ex->getTraceAsString(),
                ]
            );
        }
        $this->server->finish('');
    }

    /**
     * 任务执行完毕时调用的方法
     * @param $serv
     * @param $taskId
     * @param $data
     */
    public function onFinish($serv, $taskId, $data)
    {
        self::log('task ' . $taskId . ' finish');
    }

    /**
     * 发送回调信息
     *
     * @param $callback
     * @param $taskId
     * @param $status
     * @param $data
     */
    private function sendCallback($callback, $taskId, $status, $data)
    {
        $ret['status'] = $status;
        $ret['task_id'] = $taskId;
        $ret['data'] = json_encode($data, JSON_UNESCAPED_UNICODE);

        // 回访回调地址
        if ($callback) {
            self::log('callback:init ' . var_export($ret, true));

            $req = new Request();
            $ret = $req->post($callback, $ret);
            if ($ret->status == 200) {
                self::log('callback:result callbackSuccess');
            } else {
                self::log('callback:result callbackError ' . $ret->content);
            }
        }
    }

    /**
     * 系统结束前调用的处理器
     * @param \swoole_server $server
     */
    public function onShutdown(\swoole_server $server)
    {
        $this->killBootstrap();
    }

    /**
     * 发生错误时的处理函数
     * @param $code
     * @param $error
     * @param $file
     * @param $line
     * @param $ctx
     * @return bool
     */
    public function errorHandler($code, $error, $file, $line, $ctx)
    {
        if ($this->currentRes) {
            $this->currentRes->status(500);
            $this->returnData(
                $this->currentRes,
                self::STATUS_ERROR,
                [
                    'msg' => $error,
                    'file' => $file,
                    'line' => $line,
                ]
            );
            $this->currentRes->end();

            $this->currentRes = null;
        } else {
            trigger_error("ERROR:{$file}:${line} {$error}\n", E_USER_ERROR);
        }
        return true;
    }

    /**
     * 获取默认参数
     * @param \ReflectionMethod $refMethod
     * @param $params
     * @return array
     */
    private function getMethodArgs($refMethod, $params)
    {
        $refParams = $refMethod->getParameters();
        $args = [];
        foreach ($refParams as $param) {
            $pn = $param->getName();
            if ($params && array_key_exists($pn, $params)) {
                $args[] = $params[$pn];
            } else {
                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    $args[] = null;
                }
            }
        }
        return $args;
    }

    public function onRequest($req, $res)
    {
        $this->currentRes = $res;
        try {
            //白名单IP过滤
            if (!empty($this->permissionIp) && !in_array($req->server['remote_addr'], $this->permissionIp)) {
                //把白名单中的*换成\d+,把点转译。并用或拼接。
                $ipregexp = implode('|', str_replace(['*', '.'], ['\d+', '\.'], $this->permissionIp));
                if (!preg_match("/^" . $ipregexp . "$/", $req->server['remote_addr'])) {
                    throw new Exception('terminal.notfound');
                }
            }
            //白名单IP过滤END
            $res->header('Content-type', 'application/json; charset=utf-8');
            $uri = $req->server['request_uri'];

            list($refClass, $refMethod) = $this->callMethod($uri, 'controller');
            $ctl = $refClass->newInstance($this->server);
            $ctl->uri = $uri;
            if (!empty($req->get['callback'])) {
                $ctl->callback = rawurldecode($req->get['callback']);
                $ctl->log('callback:' . $ctl->callback);
            }
            $args = $this->getMethodArgs($refMethod, json_decode($req->rawContent(), true));

            $ctl->log('controller start');
            $ret = $refMethod->invokeArgs($ctl, $args);
            $ctl->log('controller end');

            $this->returnData($res, self::STATUS_OK, $ret, $req->get['pretty'] ?? false);
            $res->end();
        } catch (Exception $ex) {
            $message = $ex->getMessage();
            $status = self::STATUS_EXCEPTION;
            switch ($message) {
                case 'terminal.classNotFound':
                case 'terminal.methodNotFound':
                    $res->status(404);
                    $status = self::STATUS_NOTFOUND;
                    break;
                default:
                    $res->status(500);
                    break;
            }
            $this->returnData(
                $res,
                $status,
                [
                    'msg' => $ex->getMessage(),
                    'trace' => $ex->getTraceAsString(),
                ]
            );
            $res->end();
        } catch (\Exception $ex) {
            $res->status(500);
            $this->returnData(
                $res,
                self::STATUS_EXCEPTION,
                [
                    'msg' => $ex->getMessage(),
                    'trace' => $ex->getTraceAsString(),
                ]
            );
            $res->end();
        }
    }

    /**
     * 返回数据
     * @param $res
     * @param $status
     * @param string $data
     * @param bool $pretty
     */
    private function returnData($res, $status, $data = '', $pretty = false)
    {
        $ret = [
            'err' => $status,
            'data' => $data,
        ];
        $option = JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $option |= JSON_PRETTY_PRINT;
        }
        $res->write(json_encode($ret, $option));
    }

    public static function log($msg)
    {
        if (!is_scalar($msg)) {
            $msg = var_export($msg, true);
        }
        list($ms, $now) = explode(' ', microtime());
        printf('%s%s %s%s', date('Y/m/d H:i:s', $now), substr($ms, 1, 7), $msg, PHP_EOL);
    }
}
