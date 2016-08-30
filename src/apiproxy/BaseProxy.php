<?php
namespace hapn\apiproxy;

/**
 * Abstract class of Proxy
 *
 * @author    : ronnie
 * @since     : 2016/8/30 16:05
 * @copyright : 2016 jiehun.com.cn
 * @filesource: BaseProxy.php
 */
abstract class BaseProxy implements IProxy
{
    private $mod = null;
    // 协议名称，用来做不同的来源的proxy的界定
    private $protocol = '';

    /**
     * BaseProxy constructor.
     * @param $mod
     * @param string $protocol
     */
    public function __construct($mod, $protocol = '')
    {
        $this->mod = $mod;
        $this->protocol = $protocol;
    }

    /**
     * @see IProxy::init
     */
    public function init($conf, $params)
    {
    }

    /**
     * @see IProxy::call
     */
    public function call($name, $args)
    {
        return null;
    }


    /**
     * @see IProxy::getMod
     */
    public function getMod()
    {
        return $this->mod;
    }

    /**
     * Protocol
     * @return string
     */
    public function getProtocol()
    {
        return $this->protocol;
    }


    /**
     * @see IProxy::cacheable
     */
    public function cacheable()
    {
        return true;
    }
}
