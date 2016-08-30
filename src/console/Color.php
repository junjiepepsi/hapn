<?php
namespace hapn\console;

/**
 *
 * @author:     ronnie
 * @since:      16/8/19 下午11:00
 * @copyright:  2016 jiehun.com.cn
 * @filesource: Color.php
 */
;

class Color
{
    private static $foregroundColors = array(
        'black' => '0;30',
        'dark_gray' => '1;30',
        'blue' => '0;34',
        'light_blue' => '1;34',
        'green' => '0;32',
        'light_green' => '1;32',
        'cyan' => '0;36',
        'light_cyan' => '1;36',
        'red' => '0;31',
        'light_red' => '1;31',
        'purple' => '0;35',
        'light_purple' => '1;35',
        'brown' => '0;33',
        'yellow' => '1;33',
        'light_gray' => '0;37',
        'white' => '1;37',
    );
    private static $backgroundColors = array(
        'black' => '40',
        'red' => '41',
        'green' => '42',
        'yellow' => '43',
        'blue' => '44',
        'magenta' => '45',
        'cyan' => '46',
        'light_gray' => '47',
    );

    /**
     * 获取带颜色的文字
     * @param string $string black|dark_gray|blue|light_blue|green|light_green|cyan|light_cyan|red|light_red|purple|brown|yellow|light_gray|white
     * @param string|null $foregroundColor 前景颜色 black|red|green|yellow|blue|magenta|cyan|light_gray
     * @param string|null $backgroundColor 背景颜色 同$foregroundColor
     * @return string
     */
    public static function getColoredString(
        string $string,
        $foregroundColor = null,
        $backgroundColor = null
    ) {
        $coloredString = "";

        if (isset(static::$foregroundColors[$foregroundColor])) {
            $coloredString .= "\033[" . static::$foregroundColors[$foregroundColor] . "m";
        }
        if (isset(static::$backgroundColors[$backgroundColor])) {
            $coloredString .= "\033[" . static::$backgroundColors[$backgroundColor] . "m";
        }

        $coloredString .= $string . "\033[0m";

        return $coloredString;
    }

    /**
     * 输出提示信息
     * @param $msg
     */
    public static function notice(string $msg)
    {
        echo self::getColoredString($msg, 'light_gray') . PHP_EOL;
    }

    /**
     * 输出错误信息
     * @param $msg
     */
    public static function error(string $msg)
    {
        echo self::getColoredString($msg, 'red') . PHP_EOL;
    }

    /**
     * 输出警告信息
     * @param $msg
     */
    public static function warn(string $msg)
    {
        echo self::getColoredString($msg, 'yellow') . PHP_EOL;
    }

    /**
     * 输出成功信息
     * @param $msg
     */
    public static function success(string $msg)
    {
        echo self::getColoredString($msg, 'green') . PHP_EOL;
    }
}
