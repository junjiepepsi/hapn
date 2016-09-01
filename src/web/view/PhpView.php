<?php
namespace hapn\web\view;

use hapn\Exception;
use hapn\web\Application;

/**
 * View based on php
 *
 * @author    : ronnie
 * @since     : 2016/7/8 1:54
 * @copyright : 2016 jiehun.com.cn
 * @filesource: PhpView.php
 */
class PhpView implements IView
{
    public $v = [];
    private $layout;
    private $tpl;
    private $helpers = [];
    /**
     * @var Application
     */
    public $app;

    /**
     * ReflectionMethods of providers
     * @var array
     */
    private static $refMethods = [];

    /**
     * Init View
     *
     * @param Application $app
     *
     * @return mixed
     */
    public function init(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Set variable for template
     *
     * @param string $name Name of the variable
     * @param  mixed $value Value of the variable
     *
     * @return mixed
     */
    public function set(string $name, $value)
    {
        $this->v[$name] = $value;
    }

    /**
     * Set variables for template
     *
     * @param array $vars
     *
     * @return mixed
     */
    public function setArray(array $vars)
    {
        foreach ($vars as $k => $v) {
            $this->v[$k] = $v;
        }
    }

    /**
     * Output the result of compiled template
     *
     * @param string $tpl
     *
     * @return mixed
     */
    public function display(string $tpl)
    {
        if (empty($this->layout)) {
            echo $this->build($tpl);
        } else {
            $this->tpl = $tpl;
            $layout = array_shift($this->layout);
            echo $this->build($layout);
        }
    }

    /**
     * Fetch the result of compiled template
     *
     * @param string $tpl
     *
     * @return string
     * @throws Exception phpview.tplnotfound
     */
    public function build(string $tpl) : string
    {
        if (!is_readable($tpl)) {
            throw new Exception('phpview.tplnotfound tpl=' . $tpl);
        }
        ob_start();
        include $tpl;
        return ob_get_clean();
    }

    /**
     * Set layout
     *
     * @param string[] $layouts
     *
     * @return void
     */
    public function setLayout(string ...$layouts)
    {
        $this->layout = $layouts;
    }

    /**
     * Fetch the main body's content, used by the layout
     *
     * @return string
     */
    public function getBody()
    {
        $layout = array_shift($this->layout);
        if (!$layout) {
            return $this->build($this->tpl);
        } else {
            return $this->build($layout);
        }
    }

    /**
     * 动态调用helper
     *
     * @param $name
     * @param $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (isset($this->helpers[$name])) {
            $helper = $this->helpers[$name];
        } else {
            $className = $this->app->getNamespace('helper') . '\\' . ucfirst($name);
            $this->helpers[$name] = $helper = new $className($this);
        }
        return call_user_func_array([$helper, 'execute'], $arguments);
    }

    /**
     * Fetch a child template's content
     *
     * @param string $tpl
     * @param array $vars
     *
     * @return string
     */
    public function partial(string $tpl, array $vars = [])
    {
        if ($tpl[0] == '/') {
            $tpl = $this->app->getDir('view') . $tpl;
        } else {
            $dir = dirname($this->tpl);
            $tpl = $dir . '/' . $tpl;
        }
        $view = new PhpView();
        $view->init($this->app);
        $view->setArray($vars);
        return $view->build($tpl);
    }

    /**
     * Fetch data from Provider
     * @param string $provider
     * @param array $params
     * @param array $keyMap [$newKey => $returnKey]
     * @example
     *
     * <?=$this->partial(
     *   'user/list.phtml',
     *   $this->provider(
     *     'foo\bar\provider\user\List',
     *     [
     *       'team_id' => 100
     *     ]
     *   )
     * )?>
     * @return array
     */
    public function provider(string $provider, $params = [], $keyMap = [])
    {
        if ($provider[0] != "\\") {
            $provider = $this->app->getNamespace('provider') . "\\" . $provider;
        }
        $ret = self::invokeProvider($provider, $params);
        if (!$keyMap) {
            return $ret;
        }
        $newRet = [];
        foreach ($keyMap as $newKey => $oldKey) {
            if (array_key_exists($oldKey, $ret)) {
                $newRet[$newKey] = $ret[$oldKey];
            } else {
                $newKey[$newKey] = null;
            }
        }
        return $newRet;
    }

    /**
     * Invoke provider
     * @param $provider
     * @param $params
     * @return mixed
     */
    private static function invokeProvider($provider, $params)
    {
        $pos = strpos($provider, '::');
        if ($pos === false) {
            $method = 'execute';
        } else {
            $method = substr($provider, $pos + 2);
            $provider = substr($provider, 0, $pos);
        }
        $providerKey = $provider . '::' . $method;
        if (isset(self::$refMethods[$providerKey])) {
            $refMethod = self::$refMethods[$providerKey];
        } else {
            $refMethod = new \ReflectionMethod($provider, $method);
            self::$refMethods[$providerKey] = $refMethod;
        }
        $refParams = $refMethod->getParameters();
        $args = [];
        foreach ($refParams as $param) {
            $name = $param->getName();
            if (key_exists($name, $params)) {
                $args[] = $params[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
        }
        return $refMethod->invokeArgs(null, $args);
    }

    /**
     * Fetch data
     * @param string $key
     * @param mixed $def
     * @return mixed
     */
    public function data(string $key, $def = null)
    {
        return $this->app->request->getData($key, $def);
    }

    /**
     * Fetch all userDatas in app->request
     */
    public function datas()
    {
        return $this->app->request->userData;
    }
}
