<?php
namespace hapn\web\filter;

use hapn\Exception;
use hapn\util\Conf;
use hapn\util\Encoding;
use hapn\web\Application;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/7/9 18:07
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Input.php
 */
class Input implements IFilter
{
    /**
     * Request magic variables
     *
     * @var array
     */
    public static $reqVars = array(
        '_if',
        '_ie',
        '_d',
        '_of',
        '_oe',
        'route',
        '_e',
        '_try',
        '_ep',
        '_pretty',
    );

    /**
     * Execute filter
     *
     * @param Application $app
     *
     * @return bool
     */
    public function execute(Application $app)
    {
        $this->parseCommon($app);
        $this->parseInternalVar($app);
        $this->parseParams($app);
        $this->transEncoding($app);

        $requestfile = Conf::get('hapn.log.request');
        if ($requestfile) {
            $this->logRequest($app, $requestfile);
        }

        return true;
    }

    /**
     * Parse common
     *
     * @param Application $app
     *
     * @throws Exception
     */
    private function parseCommon(Application $app)
    {
        $app->request->cookies = $_COOKIE;
        $app->request->method = $_SERVER['REQUEST_METHOD'];
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $app->request->userip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $app->request->userip = $_SERVER['REMOTE_ADDR'];
        }
        $app->request->clientip = $_SERVER['REMOTE_ADDR'];

        if (isset($_GET['route'])) {
            $app->request->url = $_GET['route'];
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            if (($pos = strpos($app->request->rawUri, '?')) !== false) {
                $app->request->url = substr($app->request->rawUri, 0, $pos);
            } else {
                $app->request->url = $app->request->rawUri;
            }
        } else {
            throw new Exception(Exception::EXCEPTION_NOT_FOUND);
        }
        $app->request->url = '/' . ltrim($app->request->url, '/');
        unset($_GET['route']);
        $query = http_build_query($_GET);
        $app->request->uri = $app->request->url . ($query ? '?' . $query : '');

        $app->request->serverEnvs = $_SERVER;

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            $app->request->isAjax = true;
        }
    }

    /**
     * Parse internal variables
     *
     * @param Application $app
     */
    private function parseInternalVar(Application $app)
    {
        $app->request->appId = $app->appId;
        $arr = array_merge($_REQUEST, $_GET, $_POST);
        $app->request->pretty = empty($arr['_pretty']) ? false : true;
        if (!empty($arr['_if'])) {
            $app->request->if = $arr['_if'];
        }
        if (!empty($arr['_ie'])) {
            $app->request->if = $arr['_ie'];
        } else {
            $app->request->ie = Conf::get('hapn.inputEncoding', $app->encoding);
        }

        if (!empty($arr['_of'])) {
            $app->request->of = $arr['_of'];
        } else {
            $method = $app->request->method;
            if (in_array(
                $method,
                array(
                    'POST',
                    'PUT',
                    'DELETE'
                )
            )) {
                // 非GET请求默认都按照JSON返回了
                $app->request->of = Application::FORMAT_JSON;
            } else {
                $app->request->of = Application::FORMAT_DEFAULT;
            }
        }
        if (!empty($arr['_oe'])) {
            $app->request->oe = $arr['_oe'];
        } else {
            $app->request->oe = Conf::get('hapn.outputEncoding', $app->encoding);
        }
        if ($app->request->of === Application::FORMAT_JSON) {
            // JSON只能UTF-8
            $app->request->oe = 'UTF-8';
        }

        // 全变成大写，方便内部判断
        $app->request->ie = strtoupper($app->request->ie);
        $app->request->oe = strtoupper($app->request->oe);
    }

    /**
     * Parse params
     *
     * @param $app
     */
    private function parseParams(Application $app)
    {
        $arr = array_merge($_GET, $_POST);
        if (count($arr) != count($_GET) + count($_POST)) {
            // 有某些变量被覆盖了
            // 打一个日志警告下
            $keys = array_intersect(array_keys($_GET), array_keys($_POST));
            Logger::warning('$_GET & $_POST have same variables:%s', implode(',', $keys));
        }
        foreach (self::$reqVars as $key) {
            // 系统参数都删除
            unset($arr[$key]);
        }

        $puts = file_get_contents('php://input');
        if ($puts) {
            if (Application::FORMAT_JSON === $app->request->if) {
                $json = json_decode($puts, true);
                $arr = $arr ? array_merge($arr, $json) : $json;
            }
        }
        $app->request->inputs = $arr;
    }

    /**
     * Transform encoding
     *
     * @param Application $app
     */
    private function transEncoding(Application $app)
    {
        $ie = $app->request->ie;
        $to = $app->encoding;
        if ($ie === $to) {
            return;
        }
        // url上可能也有汉字啥的
        $app->request->url = Encoding::convert($app->request->url, $to, $ie);
        $app->request->uri = Encoding::convert($app->request->uri, $to, $ie);

        Encoding::convertArray($app->request->inputs, $to, $ie);
        // 经过转码后把转码过的数据写会，$_GET/$_POST也可能被访问
        foreach ($_GET as $key => $value) {
            if (isset($app->request->inputs[$key])) {
                $_GET[$key] = $app->request->inputs[$key];
            }
        }
        foreach ($_POST as $key => $value) {
            if (isset($app->request->inputs[$key])) {
                $_POST[$key] = $app->request->inputs[$key];
            }
        }
    }

    /**
     * Record request data
     *
     * @param Application $app
     * @param  string     $requestfile
     */
    private function logRequest(Application $app, string $requestfile)
    {
        $headers = array();
        foreach ($app->request->serverEnvs as $key => $value) {
            if (strncmp('HTTP_', $key, 5) === 0) {
                $headers[$key] = $value;
            }
        }
        $file = $app->getDir('log') . $requestfile;
        $data = $app->request->inputs;
        $keyarr = Conf::get('hapn.log.requestfilter', array());
        foreach ($keyarr as $key) {
            if (isset($data[$key])) {
                $data[$key] = '[removed]';
            }
        }
        $out = array(
            'info' => $app->appId . ':' . $app->request->url . ':' . date('Y-m-d H:i:s', $app->request->now),
            'cookie' => $app->request->cookies,
            'header' => $headers,
            'data' => $data
        );
        $dump = print_r($out, true);
        file_put_contents($file, $dump, FILE_APPEND);
    }
}
