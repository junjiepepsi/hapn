<?php
namespace hapn;

/**
 * Exception of hapn
 *
 * @author    : ronnie
 * @since     : 2016/7/6 23:15
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Exception.php
 */
class Exception extends \Exception
{
    const EXCEPTION_NOT_FOUND = 'hapn.u_notfound';
    const EXCEPTION_NO_POWER = 'hapn.u_power';
    const EXCEPTION_NOT_LOGIN = 'hapn.u_login';
    const EXCEPTION_FATAL = 'hapn.fatal';
    const EXCEPTION_INPUT = 'hapn.u_input';
    const EXCEPTION_COMMON = 'hapn.error';
    const EXCEPTION_ARGS = 'hapn.u_args';

    /**
     * page not found
     * @return Exception
     */
    public static function notFound()
    {
        return new Exception(self::EXCEPTION_NOT_FOUND);
    }

    /**
     * power
     * @return Exception
     */
    public static function noPower()
    {
        return new Exception(self::EXCEPTION_NO_POWER);
    }

    /**
     * fatal
     * @return Exception
     */
    public static function fatal()
    {
        return new Exception(self::EXCEPTION_FATAL);
    }

    /**
     * args
     * @return Exception
     */
    public static function args()
    {
        return new Exception(self::EXCEPTION_ARGS);
    }

    /**
     * input
     * @return Exception
     */
    public static function input()
    {
        return new Exception(self::EXCEPTION_INPUT);
    }

    /**
     * not login
     * @return Exception
     */
    public static function notLogin()
    {
        return new Exception(self::EXCEPTION_NOT_LOGIN);
    }
}
