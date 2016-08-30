<?php
namespace hapn\apiproxy;

use hapn\Exception;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/8/30 15:56
 * @copyright : 2016 jiehun.com.cn
 * @filesource: ApiProxy.php
 */
class ApiProxy
{
    private $caller = null;
    private static $proxies = [];
    private static $configure = [];
    private static $apppath = '';
    private static $confpath = '';
    private $intercepters = [];
    private static $globalInterceptors = [];
    private static $encoding = 'UTF-8';

    private function __construct(IProxy $proxy)
    {
        $this->caller = $proxy;
    }

    /**
     * 初始化
     * @param array $conf
     * <code>array(
     *    'mod'=>array(
     *        'class'        =>'RpcProxy|HttpRpcProxy|HttpJsonProxy',
     *        'options'    =>[]
     *    ),
     *  'server' => array(
     *
     *  ),
     * )</code>
     * @param array $pathroot
     * <code>array(
     *    'app_root' =>  , // app模块的根目录
     *    'conf_root' => , // 配置文件的根目录
     * )</code>
     */
    public static function init(array $conf, array $pathroot)
    {
        $mods = $conf['mod'];
        $servers = $conf['servers'];
        foreach ($mods as $mod => $cfg) {
            if (!empty($cfg['server'])) {
                $cfg['server'] = $servers[$cfg['server']];
            }
            self::$configure[$mod] = $cfg;
        }
        if (!empty($conf['encoding'])) {
            $encoding = strtoupper($conf['encoding']);
            self::$encoding = str_replace('-', '', $encoding);
        }

        self::$apppath = $pathroot['app_root'];
        self::$confpath = $pathroot['conf_root'];
    }

    /**
     * 设置全局的拦截器，针对每个模块有效
     *
     * @param mixed $intercepters
     * @static
     * @access public
     * @return void
     */
    public static function setGlobalInterceptors(array $intercepters)
    {
        self::$globalInterceptors = $intercepters;
    }

    /**
     * 获取模块
     * @see ANew
     * @param string $mod 模块名
     * @param array $param 初始化参数
     * @return ApiProxy
     */
    public static function getProxy($mod, array $param = [])
    {
        if (isset(self::$proxies[$mod])) {
            //支持注册一个Proxy来接口的伪实现
            //自动化测试的时候可以用到
            $proxy = self::$proxies[$mod];
        } else {
            if (!($proxy = self::getProxyFromConf($mod, $param))) {
                //默认按照php来实现
                $proxy = new PHPProxy($mod);
                $proxy->init(array(
                    'conf_path' => self::$confpath,
                    'app_path' => self::$apppath
                ), $param);
            }
            if ($proxy->cacheable()) {
                self::registerProxy($proxy);
            }
        }
        $api = new ApiProxy($proxy);
        foreach (self::$globalInterceptors as $intercepter) {
            $api->addInterceptor($intercepter);
        }
        return $api;
    }

    private static function getProxyFromConf($mod, $param)
    {
        $conf = false;
        $protocol = '';
        if (!isset(self::$configure[$mod])) {
            if (($pos = strpos($mod, '://')) !== false) {
                $protocol = substr($mod, 0, $pos);
                $key = $protocol . '://';
                if (isset(self::$configure[$key])) {
                    $conf = self::$configure[$key];
                    $mod = substr($mod, $pos + 3);
                }
            }
        } else {
            $conf = self::$configure[$mod];
        }
        if (!$conf) {
            if (isset(self::$configure['*'])) {
                $conf = self::$configure['*'];
            }
        }
        if (!$conf) {
            return false;
        }

        if (empty($conf['class'])) {
            throw new Exception('apiproxy.errconf mod=' . $mod);
        }
        $internalmod = $mod;
        if (!empty($conf['mod'])) {
            //模块重命名
            $internalmod = $conf['mod'];
        }
        $class = __NAMESPACE__."\\".$conf['class'];
        if (!empty($conf['server'])) {
            $options = $conf['server'];
        } else {
            $options = $conf;
        }
        unset($conf['class'], $conf['server'], $conf['mod']);
        $options = array_merge($options, $conf);
        //为了支持多级模块，/转换成:
        $internalmod = str_replace('/', ':', $internalmod);
        $options['encoding'] = self::$encoding;
        $object = new $class($internalmod, $protocol);
        $object->init($options, $param);
        return $object;
    }

    /**
     * 动态调用方法
     * @param $name
     * @param $args
     * @return mixed
     * @throws Exception
     */
    public function __call($name, $args)
    {
        try {
            $this->callInterceptor('before', $name, $args);

            $ret = $this->caller->call($name, $args);
            $this->callInterceptor('after', $name, $args, $ret);
            return $ret;
        } catch (Exception $ex) {
            $this->callInterceptor('exception', $name, $args);
            throw $ex;
        }
    }

    private function callInterceptor($method, $callName, $args, $ret = null)
    {
        $intercepters = $this->intercepters;
        foreach ($intercepters as $intercepter) {
            if ($method == 'after') {
                $intercepter->$method($this->caller, $callName, $args, $ret);
            } else {
                $intercepter->$method($this->caller, $callName, $args);
            }
        }
    }

    /**
     * 对每一个接口函数启用事务
     * 仅针对本地的PHP调用有效
     * 需要使用Db库
     */
    public function enableTransaction()
    {
        require_once dirname(__FILE__) . '/TrxInterceptor.php';
        $this->addInterceptor(new TrxInterceptor());
    }

    /**
     * 添加一个拦截器。类型名称相同的会被覆盖。
     * @param IInterceptor $intercepter
     * @return void
     */
    public function addInterceptor(IInterceptor $intercepter)
    {
        $classname = get_class($intercepter);
        $this->intercepters[$classname] = $intercepter;
    }

    /**
     * 删除一个拦截器。删除时会根据传入对象的类型名称来判断。
     * 如果类型相同的拦截器会被删除。
     * @param IInterceptor $intercepter
     * @return void
     */
    public function removeInterceptor(IInterceptor $intercepter)
    {
        $classname = get_class($intercepter);
        if (isset($this->intercepters[$classname])) {
            if ($intercepter == $this->intercepters[$classname]) {
                unset($this->intercepters[$classname]);
            }
        }
    }

    /**
     * 获取所有拦截器
     * @return array
     */
    public function getInterceptors()
    {
        return $this->intercepters;
    }

    /**
     * 注册一个代理
     * @param IProxy $proxy
     */
    public static function registerProxy(IProxy $proxy)
    {
        $mod = $proxy->getMod();
        $protocol = $proxy->getProtocol();
        if ($protocol) {
            $mod = $protocol . '//' . $mod;
        }
        self::$proxies[$mod] = $proxy;
    }
}
