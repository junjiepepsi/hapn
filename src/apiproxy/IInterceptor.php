<?php
/**
 * Interface of Interceptor
 *
 * @author    : ronnie
 * @since     : 2016/8/30 16:04
 * @copyright : 2016 jiehun.com.cn
 * @filesource: IInterceptor.php
 */

namespace hapn\apiproxy;

interface IInterceptor
{
    /**
     * Before method called
     * @param IProxy $proxy
     * @param $name
     * @param $args
     * @return mixed
     */
    public function before(IProxy $proxy, $name, $args);

    /**
     * After method called
     * @param IProxy $proxy
     * @param $name
     * @param $args
     * @param $ret
     * @return mixed
     */
    public function after(IProxy $proxy, $name, $args, $ret);

    /**
     * Exception found
     * @param IProxy $proxy
     * @param $name
     * @param $args
     * @return mixed
     */
    public function exception(IProxy $proxy, $name, $args);
}
