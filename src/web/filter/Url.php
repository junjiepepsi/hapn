<?php
namespace hapn\web\filter;

use hapn\Exception;
use hapn\util\Conf;
use hapn\web\Application;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/7/15 0:23
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Url.php
 */
class Url implements IFilter
{
    const METHOD_BEFORE = '_before';
    const METHOD_AFTER = '_after';

    protected $protectedMethods = [self::METHOD_BEFORE, self::METHOD_AFTER];
    const DEFAULT_ACTION = 'index';
    const CONTROLLER_FILE = 'Controller.php';
    protected $methodExt = '';

    public function __construct()
    {
        $this->methodExt = Conf::get('hapn.methodExt', '');
    }

    /**
     * Execute filter
     *
     * @param Application $app
     *
     * @return bool
     * @throws Exception
     */
    public function execute(Application $app)
    {
        $url = substr($app->request->url, strlen($app->request->prefix));

        $segs = array_diff(explode('/', trim($url, '/')), ['']);
        $args = [];
        $method = $app->request->method;
        foreach ($segs as $key => $seg) {
            if ($seg == '..') {
                throw new Exception('router.u_illegalRouter');
            }
            if (preg_match('#^(\d+)|([a-z0-9]{24,24}|[a-z0-9]{32,32}|[a-z0-9]{40,40})$#', $seg)) {
                $args[] = $seg;
                unset($segs[$key]);
            }
        }
        $segs = array_values($segs);

        $segNum = count($segs);
        if ($segNum > 0 && in_array($segs[$segNum - 1], $this->protectedMethods)) {
            throw new Exception(Exception::EXCEPTION_NOT_FOUND);
        }

        $ctlRoot = $app->getDir("controller");
        $fullpath = $ctlRoot . implode('/', $segs);
        if (is_readable($fullpath . '/' . self::CONTROLLER_FILE)) {
            $path = $fullpath . '/' . self::CONTROLLER_FILE;
            $func = self::DEFAULT_ACTION;
            if (empty($args)) {
                $args[] = '';
            }
        } else {
            if (is_readable($fullpath . '/' . self::DEFAULT_ACTION . '/' . self::CONTROLLER_FILE)) {
                $path = $fullpath . '/' . self::DEFAULT_ACTION . '/' . self::CONTROLLER_FILE;
                $func = self::DEFAULT_ACTION;
                if (empty($args)) {
                    $args[] = '';
                }
                array_push($segs, self::DEFAULT_ACTION);
            } else {
                if ($segNum > 0) {
                    // one level at least
                    $rootSeg = array_slice($segs, 0, $segNum - 1);
                    $path = rtrim($ctlRoot . implode('/', $rootSeg), '/') . '/' . self::CONTROLLER_FILE;
                    $func = $segs[$segNum - 1];

                    array_pop($segs);
                } else {
                    $path = rtrim($ctlRoot, '/') . '/' . self::CONTROLLER_FILE;
                    $func = self::DEFAULT_ACTION;
                }
            }
        }

        if (!is_readable($path)) {
            throw new Exception(Exception::EXCEPTION_NOT_FOUND . ' path not readable:' . $path);
        }
        $ctlNsRoot = $app->getNamespace('controller');
        $clsName = rtrim($ctlNsRoot . "\\" . implode("\\", $segs), "\\") . "\\Controller";

        try {
            $ctl = new $clsName();
        } catch (\Exception $ex) {
            throw new Exception(Exception::EXCEPTION_NOT_FOUND . ' class not found:' . $clsName);
        }
        $ctl->request = $app->request;
        $ctl->response = $app->response;
        $ctl->encoding = $app->encoding;
        $ctl->debug = $app->debug;
        $ctl->appId = $app->appId;

        if ($func != self::DEFAULT_ACTION) {
            $args = array_diff($args, array(''));
        }

        $mainMethod = $this->loadMethod($ctl, $func, $args);
        if (!$mainMethod) {
            if ($method == 'GET' && $func != self::DEFAULT_ACTION) {
                array_unshift($args, $func);
                $mainMethod = $this->loadMethod($ctl, self::DEFAULT_ACTION, $args);

                if (!$mainMethod) {
                    throw new Exception(Exception::EXCEPTION_NOT_FOUND . ' func not found:' . $func);
                }
            } else {
                throw new Exception(Exception::EXCEPTION_NOT_FOUND . ' func not found:' . $func);
            }
        }

        $ctl->method = $func;
        $ctl->args = $args;

        // call method _before
        if (is_callable(array($ctl, self::METHOD_BEFORE))) {
            call_user_func([$ctl, self::METHOD_BEFORE]);
        }

        // call main action method
        $mainMethod->invokeArgs($ctl, $args);

        // call method _after
        if (is_callable([$ctl, self::METHOD_AFTER])) {
            call_user_func([$ctl, self::METHOD_AFTER]);
        }

        return true;
    }

    /**
     * Load reflection method
     *
     * @param mixed $controller
     * @param string $method
     * @param array $args
     *
     * @return \ReflectionMethod|false
     * @throws Exception
     */
    private function loadMethod($controller, $method, $args = [])
    {
        $method = $method . $this->methodExt;
        if (is_callable([$controller, $method])) {
            $reflection = new \ReflectionMethod($controller, $method);
            $argnum = $reflection->getNumberOfParameters();
            if ($argnum > count($args)) {
                // check if param has default value
                $params = array_slice($reflection->getParameters(), $argnum);
                $pass = true;
                foreach ($params as $param) {
                    if (!$param->isDefaultValueAvailable()) {
                        $pass = false;
                        break;
                    }
                }
                if (!$pass) {
                    throw new Exception(Exception::EXCEPTION_NOT_FOUND . ' args not match');
                }
            }
            return $reflection;
        }
        return false;
    }
}
