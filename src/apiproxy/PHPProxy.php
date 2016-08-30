<?php
namespace hapn\apiproxy;

use hapn\util\Conf;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/8/30 16:02
 * @copyright : 2016 jiehun.com.cn
 * @filesource: PHPProxy.php
 */
class PHPProxy extends BaseProxy
{
    private $srcCaller = null;

    /**
     * @see IProxy::init
     */
    public function init($conf, $params)
    {
        $confroot = $conf['conf_path'];
        $nsroot = $conf['app_path'];

        $mod = $this->getMod();
        //支持多级的子模块
        $modseg = explode('/', $mod);
        //自动加载app mod conf
        Conf::load($confroot . implode('/', $modseg) . '.conf.php');
        //类名以每一级单词大写开始
        $class = rtrim($nsroot, "\\") . "\\" . implode("\\", $modseg) . '\\Export';

        $this->srcCaller = new $class($params);
    }

    /**
     * @see IProxy::call
     */
    public function call($name, $args)
    {
        $ret = call_user_func_array(array($this->srcCaller, $name), $args);
        return $ret;
    }
}
