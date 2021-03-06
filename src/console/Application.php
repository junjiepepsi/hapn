<?php
namespace hapn\console;

use hapn\Exception;
use hapn\util\Conf;
use hapn\util\Logger;

/**
 * Application of console
 *
 * @author    : ronnie
 * @since     : 2016/8/19 23:12:29
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Application.php
 */
class Application extends \hapn\Application
{

    private $opts = [];
    private $args = [];

    /**
     * Initial
     */
    protected function init()
    {
        // init web request
        $this->appId = $this->genAppId();

        $this->initAppEnv();
        $this->initConf();

        if ($this->debug !== true &&
            (isset($this->opts['D']) || isset($this->opts['debug']))
        ) {
            $this->debug = true;
        }

        $this->initEnv();
        $this->initLog();

        if (true !== Conf::get('hapn.disable_db')) {
            //没有强制关闭
            $this->initDB();
        }
    }

    /**
     * init app env
     */
    public function initAppEnv()
    {
        $this->opts = $opts = getopt('hc:d:D', ['help', 'debug']);
        if (empty($opts) || isset($opts['h']) || isset($opts['help'])) {
            $this->help();
        }

        if (!isset($opts['c'])) {
            Color::error('参数c必须提供');
            $this->endStatus = self::ENDSTATUS_INIT;
            $this->help(1);
        }

        $args = array();
        if (isset($opts['d'])) {
            if (is_string($opts['d'])) {
                $this->parseArg($opts['d'], $args);
            } else {
                if (is_array($opts['d'])) {
                    foreach ($opts['d'] as $opt) {
                        $this->parseArg($opt, $args);
                    }
                }
            }
        }
        $this->args = $args;
    }

    /**
     * @see parent::errorHandler
     */
    public function errorHandler()
    {
        $error = func_get_args();
        if (false === parent::errorHandler($error[0], $error[1], $error[2], $error[3])) {
            return;
        }
        if (true === $this->debug) {
            unset($error[4]);
            fwrite(STDERR, PHP_EOL . 'error found:' . PHP_EOL);
            fwrite(STDERR, "appId:" . $this->appId . PHP_EOL);
            fwrite(STDERR, "detail:" . print_r($error, true) . PHP_EOL);
        }
        exit(8);
    }

    /**
     * @param Exception $ex
     * @see parent::exceptionHandler
     */
    public function exceptionHandler($ex)
    {
        parent::exceptionHandler($ex);

        fwrite(STDERR, 'exception found:'.PHP_EOL);
        fwrite(STDERR, "appId:".$this->appId.PHP_EOL);
        fwrite(STDERR, "msg:".$ex->getMessage() . PHP_EOL);
        if ($this->debug) {
            fwrite(STDERR, "trace:" . PHP_EOL);
            fwrite(STDERR, $ex->__toString().PHP_EOL);
        }
        echo PHP_EOL;
        exit(4);
    }

    /**
     * process
     */
    protected function process()
    {
        $className = $this->opts['c'];
        $cls = new $className;
        if (!method_exists($cls, 'execute')) {
            Color::error('方法execute不存在');
            $this->endStatus = self::ENDSTATUS_INIT;
            $this->help(1);
        }
        $rMethod = new \ReflectionMethod($cls, 'execute');
        $params = $rMethod->getParameters();
        $_opts = array();
        foreach ($params as $param) {
            $name = $param->getName();
            if (isset($this->args[$name])) {
                $_opts[] = $this->args[$name];
            } else {
                if ($param->isDefaultValueAvailable()) {
                    $_opts[] = $param->getDefaultValue();
                } else {
                    throw new Exception('console.argNotDefined arg=' . $name);
                }
            }
        }

        call_user_func_array(array($cls, 'execute'), $_opts);
    }


    /**
     * 解析参数
     *
     * @param $str
     * @param $args
     */
    private function parseArg($str, &$args)
    {
        parse_str($str, $_args);
        foreach ($_args as $_k => $_v) {
            if (isset($args[$_k])) {
                if (!is_array($args[$_k])) {
                    $args[$_k] = array($args[$_k]);
                }
                $args[$_k][] = $_v;
            } else {
                $args[$_k] = $_v;
            }
        }
    }


    /**
     * show help
     * @param int $exitCode
     */
    private function help($exitCode = 0)
    {
        echo <<<HELP
 
Usage:
./Run -c "\\hapn\\tool\\Foo" -d a=b -d foo=bar
  -c 调用的tool类，该类必须具有execute方法，会将-d传入的参数匹配到方法的参数
  -d 传入的参数，以key=value的形式设置
  -D --debug 开启调试
  -h 显示此帮助文档
 
Tool Code:
# vi app/tool/Foo.php
<?php
namespace firegit\\app\\too;
 
class Foo
{
    function execute(\$foo)
    {
        echo \$foo;
    }
}
 
Output:
bar
 
 
HELP;

        exit($exitCode);
    }
}
