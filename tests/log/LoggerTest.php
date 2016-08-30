<?php
namespace hapn\tests\log;

use hapn\util\Logger;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/7/8 1:08
 * @copyright : 2016 huimang.com
 * @filesource: LoggerTest.php
 */
class LoggerTest extends \PHPUnit_Framework_TestCase
{
    public function testLogger()
    {
        Logger::init(__DIR__, 'hapn');
        for($i = 0; $i < 4; $i++) {
            Logger::warn(str_repeat($i, 1024));
        }
//        Logger::flush();
    }
}
