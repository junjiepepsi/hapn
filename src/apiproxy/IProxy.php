<?php
namespace hapn\apiproxy;

/**
 *
 * Interface of proxy
 * @author    : ronnie
 * @since     : 2016/8/30 16:02
 * @copyright : 2016 jiehun.com.cn
 * @filesource: IProxy.php
 */
interface IProxy
{
    /**
     * Get the mode name
     * @return mixed
     */
    public function getMod();

    /**
     * Initialize
     * @param $conf
     * @param $params
     * @return mixed
     */
    public function init($conf, $params);

    /**
     * Call method
     * @param $name
     * @param $args
     * @return mixed
     */
    public function call($name, $args);

    /**
     * Cacheable
     * @return mixed
     */
    public function cacheable();
}
