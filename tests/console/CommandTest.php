<?php
namespace hapn\tests\console;

use hapn\console\Command;
use hapn\console\Crontab;
use hapn\util\Logger;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/9/2 14:35
 * @copyright : 2016 jiehun.com.cn
 * @filesource: CommandTest.php
 */
class CommandTest extends \PHPUnit_Framework_TestCase
{
    public function testCommand()
    {
        Logger::init(__DIR__, 'logger');
        $cmd = new Command('php -r "echo \$aa\'hello,world\';"');

        $cmd->setErrorCallback(function($line){
            echo 'error found:'.$line;
        });
        $cmd->setErrorFile(__DIR__.'/error.log');
        $code = $cmd->execute();
        var_dump($cmd->outputs);
        var_dump($code);
        Logger::flush();
    }

    public function testCrontab()
    {
        Logger::init(__DIR__, 'logger');
        Crontab::addTask('foo', 5, 'http://www.baidu.com/');
        Crontab::run();
        Logger::flush();
    }
}
