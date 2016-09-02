<?php

namespace hapn\web;

use hapn\apiproxy\ApiProxy;
use hapn\Exception;
use hapn\util\Conf;
use hapn\util\Logger;
use hapn\web\filter\Executor;
use hapn\web\http\Request;
use hapn\web\http\Response;

/**
 * Application of web request
 *
 * @author    : ronnie
 * @since     : 2016/7/6 23:12
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Application.php
 */
class Application extends \hapn\Application
{
    const FORMAT_JSON = 'json';
    const FORMAT_XML = 'xml';
    const FORMAT_HTML = 'html';
    const FORMAT_TEXT = 'txt';
    const FORMAT_DEFAULT = 'default';

    const ENCODING_UTF8 = 'utf-8';
    const ENCODING_GBK = 'gbk';
    const ENCODING_GB2312 = 'gb2312';


    const FILTER_INIT = 'init';
    const FILTER_INPUT = 'input';
    const FILTER_URL = 'url';
    const FILTER_OUTPUT = 'output';
    const FILTER_CLEAN = 'clean';

    const HOST_COMMON = '*';

    /**
     * @var Response
     */
    public $response;

    /**
     * @var Request
     */
    public $request;


    /**
     * @var Executor
     */
    private $filterExecutor;


    /**
     * Applications
     * @var array
     */
    private static $apps = [];

    /**
     * Application's configure
     * @var false|array
     * [
     *  'root',
     *  'ns',
     *  'host',
     *  'path'
     * ]
     */
    private $env = false;


    /**
     * Register a app
     *
     * @param string $root
     * @param string $ns
     * @param string $host
     * @param string $path
     */
    public static function register(string $root, string $ns, string $path = '/', string $host = self::HOST_COMMON)
    {
        $conf = [
            'root' => rtrim($root, DIRECTORY_SEPARATOR),
            'ns' => rtrim($ns, "\\"),
            'host' => $host,
            'path' => $path,
        ];
        if (!isset(self::$apps[$host])) {
            self::$apps[$host][$path] = $conf;
            return;
        }
        // insert $path by length of $path
        $envs = &self::$apps[$host];
        for ($i = 0, $l = count($envs); $i < $l; $i++) {
            $envPath = $envs[$i]['path'];
            if (strlen($path) <= strlen($envPath)) {
                array_splice($envs, $i, 0, $conf);
                unset($envs);
                return;
            }
        }
        array_push($envs, $conf);
        unset($envs);
    }


    /**
     * Initial
     */
    protected function init()
    {
        // init web request
        $this->appId = $this->genAppId();
        $GLOBALS['__HapN_appid'] = $this->appId;

        $this->request = new Request($this);

        $this->initAppEnv();
        $this->response = new Response($this);

        $this->initConf();

        if ($this->debug == self::APP_DEBUG_MANUAL && !empty($_GET['_d'])) {
            $this->debug = true;
        }

        $this->initEnv();
        $this->initLog();

        if (true !== Conf::get('hapn.disable_apiproxy')) {
            //没有强制关闭
            $this->initApiProxy();
        }

        if (true !== Conf::get('hapn.disable_db')) {
            //没有强制关闭
            $this->initDB();
        }

        $this->filterExecutor = new Executor($this);
        $this->initFilters();
    }

    /**
     * Initialize the app's env variable
     */
    public function initAppEnv()
    {
        $host = $this->request->host;
        $uri = $this->request->rawUri;

        if (!isset(self::$apps[$host])) {
            if (!isset(self::$apps[self::HOST_COMMON])) {
                throw new Exception('hapn.hostNotRegistered host=' . $host);
            } else {
                $envs = self::$apps[self::HOST_COMMON];
            }
        } else {
            $envs = self::$apps[$host];
        }

        foreach ($envs as $env) {
            if (strpos($uri, $env['path']) === 0) {
                $this->env = $env;
                $this->request->prefix = $env['path'];
                return;
            }
        }

        throw new Exception('hapn.pathNotRegisterd');
    }

    /**
     * Process
     */
    protected function process()
    {
        foreach ([
                     self::FILTER_INIT,
                     self::FILTER_INPUT,
                     self::FILTER_URL,
                     self::FILTER_OUTPUT
                 ] as $filter) {
            if (false === $this->filter($filter)) {
                break;
            }
        }
    }

    /**
     * Initialize filters
     */
    private function initFilters()
    {
        $filters[self::FILTER_INIT] = $this->getFilter(
            'hapn.filter.' . self::FILTER_INIT,
            []
        );
        $filters[self::FILTER_INPUT] = $this->getFilter(
            'hapn.filter.' . self::FILTER_INPUT,
            ["hapn\\web\\filter\\Input"]
        );
        $filters[self::FILTER_URL] = $this->getFilter(
            'hapn.filter.' . self::FILTER_URL,
            ['\\hapn\\web\\filter\\Url'],
            true
        );
        $filters[self::FILTER_OUTPUT] = $this->getFilter(
            'hapn.filter.' . self::FILTER_OUTPUT,
            ['\\hapn\\web\\filter\\Output']
        );
        $filters[self::FILTER_CLEAN] = $this->getFilter(
            'hapn.filter.' . self::FILTER_CLEAN,
            []
        );

        $this->filterExecutor->loadFilters($filters);
    }

    /**
     * Initialize ApiProxy
     */
    protected function initApiProxy()
    {
        $servers = Conf::get('apiproxy.servers', array());
        if ($this->debug) {
            foreach ($servers as $key => $server) {
                //如果调试模式
                $servers[$key]['curlopt'][CURLOPT_VERBOSE] = true;
            }
        }
        $modmap = Conf::get('apiproxy.mod', array());
        ApiProxy::init(
            [
                'servers' => $servers,
                'mod' => $modmap,
                'encoding' => $this->encoding,
            ],
            [
                'app_root' => $this->getNamespace("app"),
                'conf_root' => defined('CONF_ROOT') ? CONF_ROOT : $this->getDir('conf') . 'app/',
            ]
        );
        $intercepterclasses = Conf::get('apiproxy.intercepters', array());
        $intercepters = array();
        foreach ($intercepterclasses as $class) {
            $intercepters[] = new $class();
        }
        ApiProxy::setGlobalInterceptors($intercepters);
    }

    /**
     * Fetch filter
     *
     * @param string $name
     * @param array $defaults
     * @param boolean $cover
     *
     * @return array
     */
    private function getFilter(string $name, array $defaults, bool $cover = false)
    {
        $filters = Conf::get($name, []);
        if (is_string($filters)) {
            $filters = [
                $filters
            ];
        }
        if ($cover) {
            if ($filters) {
                return $filters;
            } else {
                return $defaults;
            }
        } else {
            $defaults = array_diff($defaults, $filters);
            return array_merge($defaults, $filters);
        }
    }

    public function filter($filterName)
    {
        return $this->filterExecutor->execute($filterName);
    }

    /**
     * Fetch namespace
     * @param $name
     * @return string
     */
    public function getNamespace($name)
    {
        return $this->env['ns'] . '\\' . $name;
    }

    /**
     * Fetch dir
     * @param $name
     * @return string
     */
    public function getDir($name)
    {
        switch ($name) {
            case 'log':
                if (defined('LOG_ROOT')) {
                    return LOG_ROOT;
                }
                break;
            case 'conf':
                if (defined('CONF_ROOT')) {
                    return CONF_ROOT;
                }
                break;
        }
        return $this->env['root'] . DIRECTORY_SEPARATOR . $name . '/';
    }


    /**
     * Error handler
     */
    public function errorHandler()
    {
        $error = func_get_args();
        if (false === parent::errorHandler($error[0], $error[1], $error[2], $error[3])) {
            return;
        }

        // 清理掉所有的输出
        while (ob_get_clean()) {
            //
        }
        ob_start();
        if (true === $this->debug) {
            unset($error[4]);
            echo "<pre>";
            print_r($error);
            echo "</pre>";
        }
        $errcode = Exception::EXCEPTION_FATAL;
        $this->endStatus = $errcode;
        $this->response->setFrHeader($errcode);
        $this->setHeader($errcode);
        if ($this->request->needErrorPage()) {
            $this->goErrorPage($errcode);
        } else {
            $this->response->setError($error);
            $this->response->send();
        }
        exit(8);
    }

    /**
     * 转到错误页面
     *
     * @param string $errcode
     */
    private function goErrorPage($errcode)
    {
        $conf = Conf::get('hapn.error.redirect', []);
        $url = '';
        if (isset($conf[$errcode])) {
            $url = $conf[$errcode];
        } elseif (isset($conf[Exception::EXCEPTION_COMMON])) {
            $url = $conf[Exception::EXCEPTION_COMMON];
        }
        if ($url) {
            $domain = $this->request->host;
            if (strncmp($domain, 'http', 4) !== 0) {
                $domain = 'http://' . $domain;
            }
            if (is_array($url)) {
                $status = $url['status'];
                $url = $url['url'];
                $this->response->setHeader($status);
            }
            $url = str_replace('[url]', urlencode($domain . $this->request->rawUri), $url);
        }

        if (true === $this->debug) {
            $this->response->sendHeaders();
            if ($url && $url[0] = '!') {
                $url = substr($url, 1);
            }
            if ($url) {
                echo "<br/>Redirect: <a href='$url'>$url</a><br/>";
            }
        } else {
            if ($url) {
                if ($url[0] == '!') {
                    $url = substr($url, 1);
                    try {
                        $this->showInnerPage($url, $errcode);
                        exit();
                    } catch (\Exception $ex) {
                        echo $ex->getMessage();
                        echo '<pre>'.$ex->getTraceAsString().'</pre>';
                        Logger::fatal($ex->getTraceAsString());
                        exit();
                    }
                }

                $metaUrl = "<meta http-equiv=\"refresh\" content=\"0; url={$url}\"/>";
                $redirectDesc = "Redirect to:{$url}";
            } else {
                $metaUrl = "";
                $redirectDesc = "";
            }
            echo <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Error Found:{$errcode}</title>
{$metaUrl}
</head>
<body>
  <h1>Error Found:</h1>
  <p>Exception:{$errcode}</p>
  <p>{$redirectDesc}</p>
  <em>HapN</em>
</body>
</html>
HTML;
        }
    }

    /**
     * Display inner page
     * @param string $url
     * @param string $errcode
     * @throws \Exception
     */
    private function showInnerPage($url, $errcode)
    {
        $arr = explode('/', trim($url, '/'));
        $method = array_pop($arr);
        $clsName = trim($this->getNamespace('controller') . "\\" . implode("\\", $arr), "\\") . "\\Controller";
        $ctl = new $clsName();
        $func = $method.Conf::get('hapn.methodExt', '');
        if (!method_exists($ctl, $func)) {
            throw new \Exception('app.funcNotFound func:' . $func);
        }
        $this->response->reset();
        $ctl->response = $this->response;
        $ctl->request = $this->request;
        switch ($errcode) {
            case Exception::EXCEPTION_NOT_FOUND:
                $ctl->response->setHeader('HTTP/1.1 404 Not Found');
                break;
            case Exception::EXCEPTION_NO_POWER:
                $ctl->response->setHeader('HTTP/1.1 401 Unauthorized');
                break;
            case Exception::EXCEPTION_FATAL:
                $ctl->response->setHeader('HTTP/1.1 500 Internal Server Error');
                break;
        }
        $ctl->sets(
            [
                'rawUri' => $this->request->rawUri,
                'code' => $errcode,
            ]
        );
        $ctl->path = implode('/', $arr);
        $ctl->method = $method;
        if (method_exists($ctl, '_before')) {
            call_user_func(array($ctl, '_before'));
        }
        call_user_func(array($ctl, $func));
        if (method_exists($ctl, '_after')) {
            call_user_func(array($ctl, '_after'));
        }
        $ctl->response->send();
    }

    protected function setHeader($errcode)
    {
        switch ($errcode) {
            case Exception::EXCEPTION_NOT_FOUND:
                $this->response->setHeader('HTTP/1.1 404 Not Found');
                break;
            case Exception::EXCEPTION_NO_POWER:
                $this->response->setHeader('HTTP/1.1 401 Unauthorized');
                break;
            case Exception::EXCEPTION_FATAL:
                if (!($this->isUserErr($errcode))) {
                    $this->response->setHeader('HTTP/1.1 500 Internal Server Error');
                }
                break;
        }
    }

    /**
     * 是否为用户错误，默认使用\.u_作为用户错误的标识
     *
     * @param string $errcode
     *
     * @return boolean
     */
    public function isUserErr($errcode)
    {
        $usererr = Conf::get('hapn.error.userreg', '/\.u_/');
        return preg_match($usererr, $errcode) > 0;
    }


    /**
     * Exception handler
     *
     * @param \Exception $ex
     */
    public function exceptionHandler($ex)
    {
        parent::exceptionHandler($ex);
        // 清理掉所有的输出
        while (ob_get_clean()) {
            //
        }
        ob_start();

        if (true === $this->debug) {
            echo "<pre>";
            echo "<h1>{$ex->getMessage()}</h1>";
            print_r($ex->getTraceAsString());
            echo "</pre>";
        }

        $errcode = $ex->getMessage();
        if ($ex instanceof Exception && ($pos = strpos($errcode, ' '))) {
            $errcode = substr($errcode, 0, $pos);
        }

        if ($this->request->method == 'GET') {
            $retrycode = Conf::get('hapn.error.retrycode', '/\.net_/');
            $retrynum = $this->request->get('_retry', 0);
            $retrymax = Conf::get('hapn.error.retrymax', 1);
            if ($retrycode && $retrynum < $retrymax && preg_match($retrycode, $errcode) > 0) {
                $retrynum++;
                $gets = array_merge(
                    $_GET,
                    array(
                        '_retry' => $retrynum
                    )
                );
                $url = $this->request->url . '?' . http_build_query($gets);
                $this->response->setHeader('X-Rewrite-URI: ' . $url);
                $this->response->send();
                exit(4);
            }
        }
        $this->response->setFrHeader($errcode);
        $this->setHeader($errcode);

        if ($this->request->needErrorPage()) {
            $this->goErrorPage($errcode);
            exit(4);
        }
        $this->response->setException($ex);
        $this->response->send();
        exit(4);
    }
}
