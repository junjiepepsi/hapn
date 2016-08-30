<?php
namespace hapn\tests\apiproxy\app\foo\bar;

use hapn\util\Conf;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/8/30 18:43
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Export.php
 */
class Export
{
    public function submod()
    {
        return [
            'str' => 'I\'m a submodule',
            'conf' => Conf::get('foo.bar.str'),
        ];
    }
}