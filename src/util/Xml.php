<?php
namespace hapn\util;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/7/7 21:48
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Xml.php
 */
class Xml
{
    private static $search = ['&', '>', '<', '"', "'"];
    private static $replace = ['&amp;', '&gt;', '&lt;', '&quot;', '&apos;'];

    /**
     * @param $arr
     * @param $ret
     * @param $level
     */
    private static function innerArray2Xml($arr, &$ret, $level)
    {
        foreach ($arr as $key => $value) {
            if (is_numeric($key)) {
                $key = 'item';
            }
            if (is_array($value)) {
                $ret[] = str_repeat("\t", $level - 1);
                $ret[] = "<$key>\r\n";
                self::innerArray2Xml($value, $ret, $level + 1);
                $ret[] = str_repeat("\t", $level - 1);
                $ret[] = "</$key>\r\n";
            } else {
                $value = str_replace(self::$search, self::$replace, $value);
                $ret[] = str_repeat("\t", $level - 1);
                $ret[] = "<$key>$value</$key>\r\n";
            }
        }
    }

    /**
     * 将数组转化为xml
     *
     * @param array  $arr
     * @param string $encoding
     *
     * @return string
     */
    public static function array2Xml(array $arr, string $encoding)
    {
        $ret = ["<?xml version=\"1.0\" encoding=\"$encoding\" ?>\r\n"];
        if ($arr) {
            $data = ['HapN' => $arr];
            self::innerArray2Xml($data, $ret, 1);
        }
        return implode('', $ret);
    }
}
