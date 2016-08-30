<?php
namespace hapn;

use hapn\util\Conf;
use hapn\util\Logger;
use hapn\util\Timer;

/**
 * Application of hapn
 *
 * @author    : ronnie
 * @since     : 2016/7/6 23:15
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Exception.php
 */
abstract class Application
{
    const APP_DEBUG_ENABLE = true;
    const APP_DEBUG_DISABLE = false;
    const APP_DEBUG_MANUAL = 'manual';

    const ENDSTATUS_OK = 'ok';
    const ENDSTATUS_INIT = 'init';
    const ENDSTATUS_ERROR = 'error';
    /**
     * Unique ID of the application
     *
     * @var int
     */
    public $appId;

    /**
     * Application's timer
     *
     * @var Timer
     */
    public $timer;

    /**
     * encoding
     *
     * @var string
     */
    public $encoding = 'UTF-8';

    /**
     * end status
     *
     * @var string
     */
    public $endStatus = self::ENDSTATUS_INIT;

    /**
     * 是否允许调试，可选值 true/false/munual
     * false: 不允许调试
     * true： 允许调试
     * munual： 手动，通过传入_d参数启动
     *
     * @var boolean|string
     */
    public $debug = false;

    public function __construct()
    {
        $this->timer = new Timer();
    }

    private $errorTypeMap = [
        E_USER_NOTICE => Logger::LOG_LEVEL_TRACE,
        E_USER_WARNING => Logger::LOG_LEVEL_WARN,
        E_USER_ERROR => Logger::LOG_LEVEL_FATAL,
    ];

    /**
     * Run application.The entrence of application
     */
    public function run()
    {
        $this->timer->begin('total', 'init');
        $this->init();
        $this->timer->end('init');
        $this->timer->begin('process');
        $this->process();
        $this->timer->end('process');
        $this->endStatus = self::ENDSTATUS_OK;
    }

    /**
     * init
     * @return mixed
     */
    abstract protected function init();

    /**
     * process
     * @return mixed
     */
    abstract protected function process();

    /**
     * Initialize environment
     */
    protected function initEnv()
    {
        mb_internal_encoding($this->encoding);
        iconv_set_encoding('internal_encoding', $this->encoding);
        error_reporting(E_ALL | E_STRICT | E_NOTICE);
        //
        set_error_handler([$this, 'errorHandler']);
        set_exception_handler([$this, 'exceptionHandler']);
        register_shutdown_function([$this, 'shutdownHandler']);

        if ($this->debug) {
            ini_set('display_errors', 1);
        }
    }

    /**
     * 是否为用户错误，默认使用\.u_作为用户错误的标识
     *
     * @param string $errcode
     *
     * @return boolean
     */
    public function isUserErr($errcode)
    {
        $usererr = Conf::get('hapn.error.userreg', '/\.u_/');
        return preg_match($usererr, $errcode) > 0;
    }


    /**
     * Initialize configure info
     */
    protected function initConf()
    {
        $confs = array(
            (defined('CONF_ROOT') ? CONF_ROOT : $this->getDir('conf')) . '/hapn.conf.php',
        );
        Conf::load($confs);

        $this->debug = Conf::get('hapn.debug', self::APP_DEBUG_MANUAL);
        $this->encoding = strtoupper(Conf::get('hapn.encoding', $this->encoding));
    }

    /**
     * Initialize log
     */
    public function initLog()
    {
        $logFile = Conf::get('hapn.log.file', 'hapn');
        $logLevel = Conf::get('hapn.log.level', $this->debug ? Logger::LOG_LEVEL_DEBUG : Logger::LOG_LEVEL_TRACE);
        if ($this->debug === true) {
            // set log level as debug if in debug mode
            $logLevel = Logger::LOG_LEVEL_DEBUG;
        }
        $roll = Conf::get('hapn.log.roll', Logger::NONE_ROLLING);
        Logger::init(defined('LOG_ROOT') ? LOG_ROOT : $this->getDir('log'), $logFile, array(), $logLevel, $roll);

        $basic = ['appid' => $this->appId];
        Logger::addBasic($basic);
    }

    /**
     * Initialize database
     */
    public function initDB()
    {
        $conf = Conf::get('db.conf');
        $readonly = Conf::get('db.readonly', false);
        \hapn\db\Db::init($conf);
        \hapn\db\Db::setReadOnly(!!$readonly);
    }

    /**
     * Generate unique appId
     *
     * @return int
     */
    protected function genAppId()
    {
        $time = gettimeofday();
        $time = $time['sec'] * 100 + $time['usec'];
        $rand = mt_rand(1, $time + $time);
        $id = ($time ^ $rand) & 0xFFFFFFFF;
        return floor($id / 100) * 100;
    }

    /**
     * 错误处理函数
     *
     * @return boolean 如果返回false，标准错误处理处理程序将会继续调用
     */
    public function errorHandler()
    {
        $error = func_get_args();
        restore_error_handler();

        if (!($error[0] & error_reporting())) {
            Logger::debug(
                'caught info, errno:%d,errmsg:%s,file:%s,line:%d',
                $error[0],
                $error[1],
                $error[2],
                $error[3]
            );
            set_error_handler(array($this, 'errorHandler'));
            return false;
        }

        if (isset($this->errorTypeMap[$error[0]])) {
            $logLevel = $this->errorTypeMap[$error[0]];
            call_user_func(
                'Logger::' . $logLevel,
                'caught ' . $logLevel . ', errno:%d,errmsg:%s,file:%s,line:%d',
                $error[0],
                $error[1],
                $error[2],
                $error[3]
            );
            set_error_handler([$this, 'errorHandler']);
            return false;
        }

        $errmsg =
            sprintf(
                'caught error, errno:%d,errmsg:%s,file:%s,line:%d',
                $error[0],
                $error[1],
                $error[2],
                $error[3]
            );
        Logger::fatal($errmsg);
        $this->endStatus = self::ENDSTATUS_ERROR;
        return true;
    }

    /**
     *  Inner Exception handler
     *
     * @param Exception $ex
     */
    public function exceptionHandler($ex)
    {
        restore_exception_handler();
        $errcode = $ex->getMessage();
        $errmsg = sprintf('caught exception, errcode:%s, trace: %s', $errcode, $ex->__toString());
        if (($pos = strpos($errcode, ' '))) {
            $errcode = substr($errcode, 0, $pos);
        }
        $this->endStatus = $errcode;
        if ($this->isUserErr($errcode)) {
            Logger::trace($errmsg);
        } else {
            Logger::fatal($errmsg);
        }
    }

    /**
     * Shutdown handler
     */
    public function shutdownHandler()
    {
        $result = $this->timer->getResult();
        $str[] = '[time:';
        foreach ($result as $key => $time) {
            $str[] = ' ' . $key . '=' . $time;
        }
        $str[] = ']';
        Logger::notice(implode('', $str) . ' status=' . $this->endStatus);
        Logger::flush();

        if (true !== Conf::get('hapn.disable_db')) {
            //做一些清理
            \hapn\db\TxScope::rollbackAll();
            \hapn\db\Db::close();
        }
    }
}
