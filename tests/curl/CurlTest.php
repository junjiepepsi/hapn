<?php
namespace hapn\tests\curl;

use hapn\curl\Request;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/7/9 13:52
 * @copyright : 2016 huimang.com
 * @filesource: CurlTest.php
 */
class CurlTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test GET method
     */
    public function testGet()
    {
        $curl = new Request();
        $res = $curl->get('http://192.168.56.101/', [
            CURLOPT_HTTPHEADER => [
                'host: huimang.com'
            ]
        ]);
        $this->assertEquals($res->code, 404);
    }
    
    public function testPost()
    {
        
    }
}
