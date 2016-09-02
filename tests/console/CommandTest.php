<?php

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/9/2 14:35
 * @copyright : 2016 jiehun.com.cn
 * @filesource: CommandTest.php
 */
class CommandTest extends PHPUnit_Framework_TestCase
{
    function testCommand()
    {
        \hapn\util\Logger::init(__DIR__, 'logger');
        $cmd = new \hapn\console\Command('php -r "echo \$aa\'hello,world\';"');

        $cmd->setErrorCallback(function($line){
            echo 'error found:'.$line;
        });
        $cmd->setErrorFile(__DIR__.'/error.log');
        $code = $cmd->execute();
        var_dump($cmd->outputs);
        var_dump($code);
        \hapn\util\Logger::flush();
    }
}
