<?php
/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/8/30 18:27
 * @copyright : 2016 jiehun.com.cn
 * @filesource: ApiProxyTest.php
 */

namespace hapn\tests\apiproxy;


use hapn\apiproxy\ApiProxy;

class ApiProxyTest extends \PHPUnit_Framework_TestCase
{
    function testPHPProxy()
    {
        ApiProxy::init([
            'mod' => [],
            'servers' => [],
        ], [
            'app_root' => 'hapn\tests\apiproxy\app',
            'conf_root' => __DIR__.'/conf/',
        ]);

        $ret = ApiProxy::getProxy('foo')->bar();
        $this->assertEquals($ret, ['foo' => 'bar']);

        $ret = ApiProxy::getProxy('foo/bar')->submod();
        var_dump($ret);
    }
}
