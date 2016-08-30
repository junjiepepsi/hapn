<?php
namespace hapn\db;

use hapn\Exception;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/7/8 23:17
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Db.php
 */
class Db
{
    // Splited by ID's mod
    const MOD_SPLIT = 1;
    // Splited by id's division
    const DIV_SPLIT = 2;
    // Splited by month
    const MONTH_SPLIT = 3;
    // Splited b year
    const YEAR_SPLIT = 4;
    // Splited by day
    const DAY_SPLIT = 5;

    private static $dbs = [];
    public static $txDbs = [];

    /**
     * Compress data
     *
     * @param $data
     *
     * @return string
     */
    public static function compress($data)
    {
        $lenbin = pack('L', strlen($data));
        return $lenbin . gzcompress($data, 7);
    }

    /**
     * Uncompress data
     *
     * @param $data
     *
     * @return string
     */
    public static function uncompress($data)
    {
        $len = unpack('L', substr($data, 0, 4));
        return gzuncompress(substr($data, 4), $len[1]);
    }

    /**
     * Set DB's read only mode
     *
     * @param boolean $readonly If true,then read only, not allowed to write
     */
    public static function setReadOnly($readonly = false)
    {
        DbContext::$readOnly = $readonly;
    }

    /**
     * Init Db's configure
     *
     * @param $conf array configure options are above:
     *   guid_db    optional
     *   guid_table optional
     *   splits     optional
     *   log_func   optional
     *   test_mode  optional
     *   'db_pool' => [
     *     'db11' => [
     *       'ip'       => 'ip',
     *       'port'     => 3306,
     *       'user'     => 'user',
     *       'pass'     => 'pass',
     *       'charset'  => 'utf8'
     *     ),
     *     'db2'=>xxx
     *        ....
     *   ),
     *   'dbs' => [
     *     'dbname' => 'db1',
     *     'dbname' => [
     *        'master' => 'db1',
     *        'slave'  => ['db2','db3']
     *     ]
     *   ]
     *
     * @throws Exception
     */
    public static function init($conf)
    {
        if (!is_array($conf)) {
            return;
        }
        //check db conf format
        foreach ($conf['dbs'] as $db => $dbconf) {
            if (is_string($dbconf)) {
                if (!isset($conf['db_pool'][$dbconf])) {
                    throw new Exception('db.ConfError ' . $dbconf . ' no such pool in db_pool');
                }
            } else {
                if (!isset($dbconf['master']) || !isset($dbconf['slave'])) {
                    throw new Exception('db.ConfError missing master|slave conf ' . $db);
                }
                $master = $dbconf['master'];
                $slaves = $dbconf['slave'];
                if (!isset($conf['db_pool'][$master])) {
                    throw new Exception('db.ConfError ' . $master . ' no such pool in db_pool');
                }
                foreach ($slaves as $slave) {
                    if (!isset($conf['db_pool'][$slave])) {
                        throw new Exception('db.ConfError ' . $slave . ' no such pool in db_pool');
                    }
                }
            }
        }


        DbContext::$db_pool = $conf['db_pool'];
        DbContext::$dbconf = $conf['dbs'];

        DbContext::$guidDB = $conf['guid_db'] ?? null;
        DbContext::$guidTable = $conf['guid_table'] ?? null;

        DbContext::$defaultDB = $conf['default_db'] ?? null;

        DbContext::$splits = $conf['splits'] ?? [];

        DbContext::$logFunc = $conf['log_func'] ?? null;
        DbContext::$testMode = !empty($conf['test_mode']);

        DbContext::$longQueryTime = $conf['long_query_time'] ?? 0;
    }

    /**
     * Get a instance of DB
     *
     * @param $db_name string
     *
     * @return DbImpl
     * @throws Exception
     */
    public static function get($db_name = null)
    {
        if (empty($db_name)) {
            $db_name = DbContext::$defaultDB;
        }
        $db_name = strtolower($db_name);
        if (!empty(TxScope::$txDbs[$db_name]) &&
            !empty(self::$txDbs[$db_name])
        ) {
            // Reuse db if enable transaction for $db_name
            return self::$txDbs[$db_name];
        }
        if (!isset(DbContext::$dbconf[$db_name])) {
            throw new Exception('db.ConfError no db conf ' . $db_name);
        }
        $conf = [];
        if (is_string(DbContext::$dbconf[$db_name])) {
            // When only one address for db_name
            $poolname = DbContext::$dbconf[$db_name];
            // Fetch ip/port/user/pass from db_pool
            $conf['master'] = DbContext::$db_pool[$poolname];
        } else {
            // When configured master/slave for db_name
            $poolconf = DbContext::$dbconf[$db_name];
            $mastername = $poolconf['master'];
            $conf['master'] = DbContext::$db_pool[$mastername];
            foreach ($poolconf['slave'] as $slave) {
                // Fetch ip/port/user/pass from db_pool
                $conf['slave'][] = DbContext::$db_pool[$slave];
            }
        }
        $db = new DbImpl($db_name, $conf);
        self::$dbs[$db_name][] = $db;

        if (!empty(TxScope::$txDbs[$db_name])) {
            // Keep the connection when transaction enabled
            self::$txDbs[$db_name] = $db;
        }

        return $db;
    }

    /**
     * Close the connection of db
     */
    public static function close()
    {
        foreach (self::$txDbs as $dbname => $db) {
            $db->rollback();
        }
        self::$txDbs = [];
        foreach (self::$dbs as $dbname => $arrdb) {
            foreach ($arrdb as $db) {
                $db->rollback();
            }
        }
        self::$dbs = [];
    }

    /**
     * Assign a guid
     *
     * @param string $name GUID's name, it must be created from database before use
     * @param int    $count GUID's number
     *
     * @return int The return id bigger than the max id,no matter how greate $count is
     * @throws Exception
     */
    public static function newGUID($name, $count = 1)
    {
        if (empty(DbContext::$guidDB)) {
            throw new Exception('db.GUIDError not support');
        }
        $db = self::get(DbContext::$guidDB);
        $count = intval($count);
        if ($count < 1) {
            throw new Exception("db.guid error count");
        }
        if (DbContext::$logFunc) {
            $log = '[GUID][NAME:' . $name . '][COUNT:' . $count . ']';
            call_user_func(DbContext::$logFunc, $log);
        }
        if (DbContext::$testMode) {
            return 1;
        }
        $db->forceMaster(true);
        $changeRows = $db->queryBySql(
            'UPDATE ' . DbContext::$guidDB . '.' . DbContext::$guidTable .
            ' set guid_value = LAST_INSERT_ID(guid_value+?) where guid_name = ?',
            $count,
            $name
        );
        if (!$changeRows) {
            throw new Exception('db.newGuid error guid_name:' . $name);
        }
        $res = $db->queryBySql('SELECT LAST_INSERT_ID() as ID');
        $lastId = intval($res[0]['ID']);
        return $lastId - $count + 1;
    }

    /**
     * Transform text to 64bit interger
     *
     * @param string $s
     *
     * @return int
     */
    public static function createSign64($s)
    {
        $hash = md5($s, true);
        $high = substr($hash, 0, 8);
        $low = substr($hash, 8, 8);
        $sign = $high ^ $low;
        $sign1 = hexdec(bin2hex(substr($sign, 0, 4)));
        $sign2 = hexdec(bin2hex(substr($sign, 4, 4)));
        return ($sign1 << 32) | $sign2;
    }
}
