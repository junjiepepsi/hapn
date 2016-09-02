<?php
namespace hapn\util;

use hapn\Exception;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/7/8 0:04
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Logger.php
 */
class Logger
{
    // Log's level type
    const LOG_LEVEL_DEBUG = 'debug';
    const LOG_LEVEL_TRACE = 'trace';
    const LOG_LEVEL_NOTICE = 'notice';
    const LOG_LEVEL_WARN = 'warn';
    const LOG_LEVEL_FATAL = 'fatal';

    const INT_LOG_LEVEL_DEBUG = 0x10;
    const INT_LOG_LEVEL_TRACE = 0x8;
    const INT_LOG_LEVEL_NOTICE = 0x4;
    const INT_LOG_LEVEL_WARN = 0x2;
    const INT_LOG_LEVEL_FATAL = 0x1;

    // Log's rolling type
    const NONE_ROLLING = 0;
    const HOUR_ROLLING = 1;
    const DAY_ROLLING = 2;
    const MONTH_ROLLING = 3;

    // Log's type
    const LOG_TYPE_NORMAL = 'normal';
    const LOG_TYPE_CRITICAL = 'critical';

    const BUFFER_SIZE = 4096;

    private static $logLevels = array(
        self::LOG_LEVEL_DEBUG => self::INT_LOG_LEVEL_DEBUG,
        self::LOG_LEVEL_TRACE => self::INT_LOG_LEVEL_TRACE,
        self::LOG_LEVEL_NOTICE => self::INT_LOG_LEVEL_NOTICE,
        self::LOG_LEVEL_WARN => self::INT_LOG_LEVEL_WARN,
        self::LOG_LEVEL_FATAL => self::INT_LOG_LEVEL_FATAL,
    );

    private static $basics = array();

    private static $logs = [];

    private static $level;
    private static $basicStr;

    /**
     * @param string $dir
     * @param string $file
     * @param array $info
     * @param int $level
     * 0x1:debug
     * 0x2:trace
     * 0x4:notice
     * 0x8:warn
     * 0x10:fatal
     * @param int $rollType
     *
     * @throws Exception logger.dirnotwritable
     */
    public static function init(
        string $dir,
        string $file,
        array $info = [],
        int $level = self::INT_LOG_LEVEL_DEBUG,
        int $rollType = self::NONE_ROLLING
    ) {
        if (!is_writable($dir)) {
            throw new Exception('logger.dirnotwritable');
        }

        self::$level = $level;

        $prefix = $dir . '/' . $file;
        switch ($rollType) {
            case self::DAY_ROLLING:
                $prefix .= '.' . date('Ymd');
                break;
            case self::MONTH_ROLLING:
                $prefix .= '.' . date('Ym');
                break;
            case self::HOUR_ROLLING:
                $prefix .= '.' . date('YmdH');
                break;
        }
        self::$logs[self::LOG_TYPE_NORMAL] = [
            'path' => $prefix . '.log',
            'writer' => null,
            'buffer' => '',
        ];
        self::$logs[self::LOG_TYPE_CRITICAL] = [
            'path' => $prefix . '.log.wf',
            'writer' => null,
            'buffer' => '',
        ];

        self::$basics = array_merge(self::$basics, $info);
        self::genBasicStr();
    }

    /**
     * Generate the basic str
     */
    private static function genBasicStr()
    {
        $bs = [];
        foreach (self::$basics as $key => $value) {
            $bs[] = '[' . $key . ':' . $value . ']';
        }
        self::$basicStr = implode(' ', $bs);
    }


    /**
     * Debug
     * @param array ...$args
     */
    public static function debug(...$args)
    {
        array_unshift($args, self::LOG_LEVEL_DEBUG);
        call_user_func_array(__CLASS__ . '::log', $args);
    }

    /**
     * Trace
     * @param array ...$args
     */
    public static function trace(...$args)
    {
        array_unshift($args, self::LOG_LEVEL_TRACE);
        call_user_func_array(__CLASS__ . '::log', $args);
    }

    /**
     * Notice
     * @param array ...$args
     */
    public static function notice(...$args)
    {
        array_unshift($args, self::LOG_LEVEL_NOTICE);
        call_user_func_array(__CLASS__ . '::log', $args);
    }

    /**
     * Fatal
     * @param array ...$args
     */
    public static function fatal(...$args)
    {
        array_unshift($args, self::LOG_LEVEL_FATAL);
        call_user_func_array(__CLASS__ . '::log', $args);
    }

    /**
     * Info
     * @param array ...$args
     */
    public static function warn(...$args)
    {
        array_unshift($args, self::LOG_LEVEL_WARN);
        call_user_func_array(__CLASS__ . '::log', $args);
    }

    /**
     * 添加基本变量
     *
     * @param string|array $key
     * @param string $value
     */
    public static function addBasic($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                self::$basics[$k] = $v;
            }
        } else {
            self::$basics[$key] = $value;
        }
        self::genBasicStr();
    }

    /**
     * 记录日志
     *
     * @param string $level
     * @param string $msg
     * @param array ...$args
     */
    public static function log(string $level, string $msg, ...$args)
    {
        if (empty(self::$logs)) {
            return;
        }
        if (!isset(self::$logLevels[$level])) {
            return;
        }
        $intLevel = self::$logLevels[$level];
        if ($intLevel > self::$level) {
            return;
        }
        $micro = microtime();
        $sec = intval(substr($micro, strpos($micro, " ")));
        $ms = floor($micro * 1000000);

        $bt = debug_backtrace();
        if (isset($bt [1]) && isset($bt[1]['file']) && (!isset($bt[1]['function']) || $bt[1]['function'] != 'call_user_func_array')) {
            $c = $bt [1];
        } elseif (isset($bt[2]) && isset($bt[2]['file'])) {
            $c = $bt [2];
        } elseif (isset($bt[3]) && isset($bt[3]['file'])) {
            $c = $bt [3];
        } elseif (isset($bt[0]) && isset($bt[0]['file'])) {
            $c = $bt [0];
        } else {
            $c = [
                'file' => 'faint',
                'line' => 'faint'
            ];
        }
        $line_no = '[' . $c ['file'] . ':' . $c ['line'] . '] ';
        $prefix = sprintf(
            '%s:%s.%-06d*%d%s',
            $level,
            date('Y/m/d H:i:s', $sec),
            $ms,
            posix_getpid(),
            $line_no
        );

        if (empty($args)) {
            $msg = $prefix . self::$basicStr . $msg;
        } else {
            array_unshift($args, $msg);
            $msg = $prefix . self::$basicStr . call_user_func_array('\sprintf', $args);
        }
        self::writeLog($intLevel, $msg);
    }


    /**
     * Write log
     *
     * @param int $level
     * @param string $msg
     * @param bool $forceWrite
     */
    private static function writeLog(int $level, string $msg, bool $forceWrite = false)
    {
        if ($level > self::$logLevels[self::LOG_LEVEL_WARN]) {
            self::innerWriteLog(self::$logs[self::LOG_TYPE_NORMAL], $msg, $forceWrite);
        } else {
            self::innerWriteLog(self::$logs[self::LOG_TYPE_CRITICAL], $msg, $forceWrite);
        }
    }

    /**
     * Writer log
     * @param array $log
     * @param string $msg
     * @param bool $forceWrite
     */
    private static function innerWriteLog(array &$log, string $msg, bool $forceWrite)
    {
        if ($msg) {
            $log['buffer'] .= $msg . "\n";
        }
        if (($forceWrite && $log['buffer']) || strlen($log['buffer']) >= self::BUFFER_SIZE) {
            if (!$log['writer']) {
                $log['writer'] = fopen($log['path'], 'a+');
            }
            fwrite($log['writer'], $log['buffer']);
            $log['buffer'] = '';
        }
    }

    /**
     * flush the buffer
     */
    public static function flush()
    {
        if (empty(self::$logs)) {
            return;
        }
        self::innerWriteLog(self::$logs[self::LOG_TYPE_NORMAL], '', true);
        self::innerWriteLog(self::$logs[self::LOG_TYPE_CRITICAL], '', true);

        if (is_resource(self::$logs[self::LOG_TYPE_NORMAL]['writer'])) {
            fclose(self::$logs[self::LOG_TYPE_NORMAL]['writer']);
        }
        if (is_resource(self::$logs[self::LOG_TYPE_CRITICAL]['writer'])) {
            fclose(self::$logs[self::LOG_TYPE_CRITICAL]['writer']);
        }
    }
}
