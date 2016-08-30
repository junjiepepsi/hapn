<?php
namespace hapn\apiproxy;

use hapn\db\TxScope;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/8/30 16:17
 * @copyright : 2016 jiehun.com.cn
 * @filesource: TrxInterceptor.php
 */
class TrxInterceptor implements IInterceptor
{

    /**
     * Before method called
     * @param IProxy $proxy
     * @param $name
     * @param $args
     * @return mixed
     */
    public function before(IProxy $proxy, $name, $args)
    {
        TxScope::beginTx();
    }

    /**
     * After method called
     * @param IProxy $proxy
     * @param $name
     * @param $args
     * @param $ret
     * @return mixed
     */
    public function after(IProxy $proxy, $name, $args, $ret)
    {
        TxScope::commit();
    }

    /**
     * Exception found
     * @param IProxy $proxy
     * @param $name
     * @param $args
     * @return mixed
     */
    public function exception(IProxy $proxy, $name, $args)
    {
        TxScope::rollback();
    }
}
