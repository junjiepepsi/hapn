<?php
namespace hapn\db;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/7/8 23:49
 * @copyright : 2016 jiehun.com.cn
 * @filesource: TxScope.php
 */
class TxScope
{
    public static $tx = false;
    public static $txDbs = array();

    /**
     * 启动一个全局事务。调用此函数后，后面所有的查询操作自动开启事务。
     * 但是对已经启用事务的db连接没有影响。
     * @param string $dbname 如果指定了dbname，则只对dbname启用全局事务
     * @throws \Exception
     */
    public static function beginTx($dbname = null)
    {
        if ($dbname) {
            if (!empty(self::$txDbs[$dbname])) {
                throw new \Exception('TxScope.beginTx transaction begined dbname=' . $dbname);
            }
            self::$txDbs[$dbname] = 1;
        } else {
            if (self::$tx) {
                throw new \Exception('TxScope.beginTx transaction begined');
            }
            self::$tx = true;
        }
    }

    /**
     * 提交所有正在执行的事务。同时关闭全局事务选项。
     * @param string $dbname
     */
    public static function commit($dbname = null)
    {
        if ($dbname) {
            if (isset(Db::$txDbs[$dbname])) {
                $db = Db::$txDbs[$dbname];
                $db->commit();
                unset(TxScope::$txDbs[$dbname]);
                unset(Db::$txDbs[$dbname]);
            }
        } else {
            foreach (Db::$gdps as $dbname => $arrgdp) {
                foreach ($arrgdp as $gdp) {
                    $gdp->commit();
                }
            }
            self::$tx = false;
        }
    }

    /**
     * 回滚所有全局事务。同时关闭全局事务选项.
     * @param string $dbname
     */
    public static function rollback($dbname = null)
    {
        if ($dbname) {
            if (isset(Db::$txDbs[$dbname])) {
                $db = Db::$txDbs[$dbname];
                $db->rollback();
                unset(TxScope::$txDbs[$dbname]);
                unset(Db::$txDbs[$dbname]);
            }
        } else {
            Db::close();
            self::$tx = false;
        }
    }

    /**
     * 回滚所有的事务
     */
    public static function rollbackAll()
    {
        return self::rollback();
    }
}
