<?php
namespace hapn\util;

/**
 * Encoding handler
 *
 * @author    : ronnie
 * @since     : 2016/7/15 0:16
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Encoding.php
 */
class Encoding
{
    /**
     * Convert encoding
     *
     * @param $value
     * @param $to
     * @param $from
     *
     * @return string
     */
    public static function convert($value, $to, $from)
    {
        if ($value == null) {
            return $value;
        }
        //有ccode库
        $isccode = function_exists('is_gbk');
        //专门有处理gbk/utf8转码的扩展，解决一些badcase
        if ($to === 'GBK' && ($from === 'UTF-8' || $from === 'UTF8') && $isccode) {
            $v = utf8_to_gbk($value, strlen($value), UCONV_INVCHAR_REPLACE);
            if ($v !== false) {
                return $v;
            } else {
                Logger::warn("utf8_to_gbk fail str=%s", bin2hex($value));
            }
        }
        if (($to === 'UTF-8' || $to === 'UTF8') && $from === 'GBK' && $isccode) {
            $v = gbk_to_utf8($value, strlen($value), UCONV_INVCHAR_REPLACE);
            if ($v !== false) {
                return $v;
            } else {
                Logger::warn("gbk_to_utf8 fail str=%s", bin2hex($value));
            }
        }
        //return mb_convert_encoding($value,$to,$from);
        //mb_convert会由于字符编码问题出fatal，改成iconv //ignore模式
        return iconv($from, $to . '//ignore', $value);
    }

    /**
     * Batch convert
     * @param $arr
     * @param $to
     * @param $ie
     */
    public static function convertArray(&$arr, $to, $ie)
    {
        foreach ($arr as $key => &$value) {
            if (is_array($value)) {
                self::convertArray($value, $to, $ie);
            } elseif (is_string($value)) {
                $value = self::convert($value, $to, $ie);
            }
        }
    }
}
