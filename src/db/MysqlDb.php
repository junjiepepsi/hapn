<?php
namespace hapn\db;

use hapn\Exception;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/7/8 23:52
 * @copyright : 2016 jiehun.com.cn
 * @filesource: MysqlDb.php
 */
class MysqlDb
{
    private $master;
    private $slave;
    private $dbname;

    private $forceMaster = false;

    private $mdb;

    //事务
    private $tx = false;
    private static $handleCache = [];

    /**
     * MysqlDb constructor.
     *
     * @param $dbname
     * @param $conf
     *
     * @throws Exception
     */
    public function __construct($dbname, $conf)
    {
        /**
         * @poolconf 格式参考DB::init函数的 pool格式
         * [
         *  'master'=>@poolconf,
         *  'slave'=>[@poolconf,....]
         * ]
         */
        if (empty($conf['master'])) {
            throw new Exception('db.DbConfError missing master ' . $dbname);
        }
        $this->dbname = $dbname;
        $this->master = $conf['master'];
        $this->slave = empty($conf['slave']) ? [] : $conf['slave'];
    }

    /**
     * Create Connection
     *
     * @param $conf
     *
     * @return \mysqli
     * @throws Exception
     */
    private function createConnection($conf)
    {
        $handle = mysqli_init();
        $ret = mysqli_real_connect(
            $handle,
            $conf['ip'],
            $conf['user'],
            $conf['pass'],
            $this->dbname,
            $conf['port']
        );
        if (!$ret) {
            mysqli_close($handle);
            throw new Exception('db.ConnectError ' . mysqli_error($handle));
        }
        mysqli_set_charset($handle, $conf['charset']);
        return $handle;
    }

    /**
     * Fetch db handler
     *
     * @param $isRead
     * @param $hash
     *
     * @return mixed|\mysqli
     * @throws Exception
     */
    private function getDbHandle($isRead, $hash)
    {
        if ($this->isTxBegin() && $this->mdb) {
            //事务状态下直接返回事务连接
            return $this->mdb;
        }
        if (!$isRead || //写操作
            empty($this->slave) || //没有从库
            $this->isTxBegin() || //事务操作
            $this->forceMaster
        ) { //强制为Master

            $key = $this->getCacheKey($this->master);
            if (isset(self::$handleCache[$key])) {
                //如果已经建立了连接,则复用
                $handle = self::$handleCache[$key];
            } else {
                $handle = $this->createConnection($this->master);
            }
            if (!$this->isTxBegin()) {
                //没有开启事务的状态下缓存连接，便于后续复用。
                self::$handleCache[$key] = $handle;
            } else {
                //暂时保存事务连接，并且从cache中清除，不要让别的查询进入本事务。
                $this->mdb = $handle;
                unset(self::$handleCache[$key]);
            }
            return $handle;
        } else {
            //第一级cache，根据db复用
            $key1 = 'slave_' . $this->dbname;
            if (isset(self::$handleCache[$key1])) {
                return self::$handleCache[$key1];
            }

            //一致性hash算法选择一个从库
            $index = intval(($hash % 360) / (360 / count($this->slave)));
            $slave = $this->slave[$index];

            //第二级cache根据ip.user.port复用
            $key2 = $this->getCacheKey($slave);
            if (isset(self::$handleCache[$key2])) {
                return self::$handleCache[$key2];
            }
            $handle = $this->createConnection($slave);
            self::$handleCache[$key1] = self::$handleCache[$key2] = $handle;

            return $handle;
        }
    }

    /**
     * Get the cache key
     *
     * @param $conf
     *
     * @return string
     */
    private function getCacheKey($conf)
    {
        return $conf['ip'] . $conf['user'] . $conf['port'];
    }

    /**
     * Query result
     *
     * @param $db
     * @param $sqls
     * @param $multi
     *
     * @return array
     * @throws Exception
     */
    public function queryResult($db, $sqls, $multi)
    {
        $sql = implode($sqls, ';');
        if ($multi) {
            if (!mysqli_multi_query($db, $sql)) {
                throw new Exception('db.QueryError ' . mysqli_error($db));
            }
            $results = [];
            do {
                if (mysqli_field_count($db)) {
                    if (false === ($rhandle = mysqli_store_result($db))) {
                        throw new Exception('db.QueryError ' . mysqli_error($db));
                    }

                    $rows = [];
                    while (($row = mysqli_fetch_row($rhandle))) {
                        $rows[] = $row;
                    }
                    $results[] = ['fields' => $rows];
                    mysqli_free_result($rhandle);
                } else {
                    //如果是更新的语句，返回受影响行数就可以了
                    $results[] = [
                        'affacted_rows' => mysqli_affected_rows($db),
                        'fields' => []
                    ];
                }
            } while (mysqli_more_results($db) && mysqli_next_result($db));

            return $results;
        } else {
            if (!($rhandle = mysqli_query($db, $sql))) {
                throw new Exception('db.QueryError ' . mysqli_error($db));
            }
            if (mysqli_field_count($db)) {
                $rows = [];
                //循环获取数据
                while (($row = mysqli_fetch_assoc($rhandle))) {
                    $rows[] = $row;
                }
                mysqli_free_result($rhandle);
                $ret = ['fields' => $rows];
                //看看select後面是否跟著SQL_CALC_FOUND_ROWS
                if (strtoupper(substr($sql, 7, 19)) === 'SQL_CALC_FOUND_ROWS') {
                    $foundRow = $this->query('SELECT FOUND_ROWS() as  _row');
                    $ret['found_rows'] = $foundRow[0]['fields'][0]['_row'];
                }
                return [$ret];
            } else {
                $num = mysqli_affected_rows($db);
                return [
                    [
                        'affected_rows' => $num,
                        'fields' => []
                    ]
                ];
            }
        }
    }

    /**
     * Force to use master
     *
     * @param bool $force
     */
    public function forceMaster($force = true)
    {
        $this->forceMaster = $force;
    }

    /**
     * 执行一个查询
     *
     * @param array $sqlInfo 参数为执行查询需要的sql 值等信息，格式如下
     * query('select');
     * query('select', $arg,$arg);
     * query([
     *  ['select']
     *]);
     * query([
     *      ['select1', arg,$arg],
     *      ['select2', arg,$arg],
     *  ]);
     *
     * @return array
     * @throws Exception
     * @throws \Exception
     */
    public function query($sqlInfo)
    {
        if (empty($sqlInfo)) {
            throw new Exception('db.NotAllowEmptyQuery');
        }
        if ((isset(TxScope::$txDbs[$this->dbname]) || TxScope::$tx) && !$this->tx) {
            //如果开启了全局事务并且当前还没有执行过事务
            $this->beginTx();
        }
        $isMulti = is_array($sqlInfo[0]);
        $sqls = $this->buildQuery($sqlInfo);
        //LOG SQL
        if (DbContext::$logFunc) {
            $log = '[DB:' . $this->dbname . '][SQL:' . implode(';', $sqls) . ']';
            call_user_func(DbContext::$logFunc, $log);
        }
        if (DbContext::$testMode) {
            //测试模式，直接返回空结果
            return [
                ['fields' => [], 'affected_rows' => 0, 'found_rows' => 0]
            ];
        }
        global $__HapN_appid;
        if ($__HapN_appid) {
            //在HapN框架的一个特殊支持，不用再HapN下也无影响
            $__HapN_appid++;
        }
        $isRead = $this->isRead($sqls);
        $db = $this->getDbHandle($isRead, crc32($sqls[0]));
        $results = $this->queryResult($db, $sqls, $isMulti);
        return $results;
    }

    /**
     * Build query
     *
     * @param $queryInfo
     *
     * @return array
     * @throws Exception
     */
    private function buildQuery($queryInfo)
    {
        if (is_string($queryInfo)) {
            $querys = [[$queryInfo]];
        } elseif (is_array($queryInfo[0])) {
            $querys = $queryInfo[0];
        } else {
            $querys = [$queryInfo];
        }
        $ret = [];
        foreach ($querys as $query) {
            $sql = array_shift($query);
            if (!$sql) {
                throw new Exception('db.NotAllowEmptyQuery');
            }
            if (isset($query[0]) && is_array($query[0])) {
                //如果第一个是数组，则认为所有的参数都在这个数组里面
                $query = $query[0];
            }
            $argnum = count($query);
            if (substr_count($sql, '?') > $argnum) {
                throw new Exception("db.MysqlSqlParam:$sql Error");
            }
            if ($argnum > 0) {
                $format_sql = str_replace('?', '%s', $sql);
                $ret[] = vsprintf($format_sql, $this->escapeValues($query));
            } else {
                $ret[] = $sql;
            }
        }
        return $ret;
    }

    /**
     * @param $arr
     *
     * @return mixed
     * @throws Exception
     */
    public function escapeValues($arr)
    {
        foreach ($arr as &$v) {
            $v = $this->escapeValue($v);
        }
        return $arr;
    }

    /**
     * Escape sql statement
     *
     * @param     $value
     * @param int $level
     *
     * @return int|mixed|string
     * @throws Exception
     */
    public function escapeValue($value, $level = 0)
    {
        if (is_int($value)) {
            return $value;
        } elseif (is_string($value)) {
            $hex_value = bin2hex($value);
            return "unhex('$hex_value')";
        } elseif (is_numeric($value)) {
            if (0 == $value) {
                return "'0'";
            }
            return $value;
        } elseif (is_null($value)) {
            return 'NULL';
        } elseif (is_bool($value)) {
            //布尔值返回1/0
            return $value ? 1 : "'0'";
        } elseif (is_array($value) && $level < 2) {
            if (isset($value['exp']) && is_string($value['exp'])) {
                //支持字段值为exp表达式
                return $value['exp'];
            } else {
                $arr = [];
                foreach ($value as $v) {
                    $arr[] = $this->escapeValue($v, $level + 1);
                }
                return implode(',', $arr);
            }
        } else {
            throw new Exception('db.EscapeValue not support type');
        }
    }

    /**
     * Whether a read query
     *
     * @param $sql
     *
     * @return bool
     */
    private function isRead($sql)
    {
        if (is_array($sql)) {
            foreach ($sql as $s) {
                if (!$this->isRead($s)) {
                    return false;
                }
            }
            return true;
        }

        /* 判断该sql语句的前六个字符是否是 SELECT */
        $sql = strtoupper($sql);
        if (strncmp('SELECT', $sql, 6) === 0 || strncmp('DESC', $sql, 4) === 0) {
            return true;
        }
        return false;
    }

    /**
     * @return array
     * @throws Exception
     * @throws \Exception
     */
    public function beginTx()
    {
        if ($this->tx) {
            throw new Exception('db.Transaction already begins');
        }
        $this->tx = true;
        try {
            return $this->query('START TRANSACTION');
        } catch (\Exception $ex) {
            $this->tx = false;
            throw $ex;
        }
    }

    /**
     * Stop transaction
     *
     * @param $sql
     *
     * @return array|false
     * @throws Exception
     */
    private function stopTrans($sql)
    {
        if (!$this->tx) {
            return false;
        }

        $ret = $this->query($sql);
        $this->tx = false;

        //释放事务连接
        $key = $this->getCacheKey($this->master);
        self::$handleCache[$key] = $this->mdb;
        $this->mdb = null;

        return $ret;
    }

    /**
     * Commit
     *
     * @return array|void
     */
    public function commit()
    {
        return $this->stopTrans('COMMIT');
    }

    /**
     * Rollback
     *
     * @return array|void
     */
    public function rollback()
    {
        return $this->stopTrans('ROLLBACK');
    }

    /**
     * Whether the transaction begin
     *
     * @return bool
     */
    public function isTxBegin()
    {
        return $this->tx;
    }

    /**
     * 删除缓存，用来重新请求mysql资源
     */
    public static function destroyHandleCache()
    {
        self::$handleCache = [];
    }
}
