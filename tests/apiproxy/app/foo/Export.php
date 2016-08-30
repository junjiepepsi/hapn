<?php
namespace hapn\tests\apiproxy\app\foo;

use hapn\util\Conf;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/8/30 18:33
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Export.php
 */
class Export
{
    public function bar()
    {
        $foo = Conf::get('foo.bar');
        return [
            'foo' => $foo,
        ];
    }
}