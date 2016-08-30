<?php
namespace hapn\util;

use hapn\Exception;

/**
 * 用来结合配置文件实现初始化的 组件接口
 * com库是为了PHP连接后端通用模块系统而设计的库，com本身会整合多个模块，目前整合了Cache、Sms等模块。
 *
 * @author    : ronnie
 * @since     : 2016/8/30 20:50
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Com.php
 * @example
 * 调用方式：加载一个模块并调用其函数
 * $m = \hapn\util\Com::get($mod);
 * $m->func1($args);
 */
class Com
{
    static $_conf;
    static $_cache = array();

    static function get($mod)
    {
        if (isset(self::$_cache[$mod])) {
            return self::$_cache[$mod];
        }
        if (!isset(self::$_conf[$mod])) {
            // autoload conf
            $conf = Conf::get("com.{$mod}", null);
            if ($conf === null) {
                throw new Exception("com.$mod missing conf");
            }
            self::$_conf[$mod] = $conf;
        }
        $class = "\\hapn\\{$mod}\\" . ucfirst($mod);
        $cf = self::$_conf[$mod];
        if (!empty(self::$_conf['log_func'])) {
            $cf['log_func'] = self::$_conf['log_func'];
        }
        $obj = new $class($cf);
        self::$_cache[$mod] = $obj;
        return $obj;
    }
}
