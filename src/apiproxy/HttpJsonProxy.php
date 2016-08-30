<?php
namespace hapn\apiproxy;

use hapn\Exception;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/8/30 16:18
 * @copyright : 2016 jiehun.com.cn
 * @filesource: HttpJsonProxy.php
 */
class HttpJsonProxy extends BaseProxy
{
    private $srcCaller = null;

    private $params = null;

    public function init($conf, $params)
    {
        $this->params = $params;
        $encoding = isset($conf['encoding']) ? $conf['encoding'] : 'UTF-8';
        $ctimeout = isset($conf['connect_timeout']) ? $conf['connect_timeout'] : 3000;
        $rtimeout = isset($conf['read_timeout']) ? $conf['read_timeout'] : 3000;
        $wtimeout = isset($conf['write_timeout']) ? $conf['write_timeout'] : 3000;
        $rpc = new HttpJson($conf['servers'], $encoding, $ctimeout, $rtimeout, $wtimeout);
        $this->srcCaller = $rpc;
    }

    public function call($name, $args)
    {
        if ($name == '_fetch') {
            if (!isset($args[0])) {
                throw new Exception('apiproxy.fetch missing fetch url');
            }
            $url = $args[0];
            $input = isset($args[1]) ? $args[1] : array();
            $ret = $this->srcCaller->rpcCall($url, $input);
            return $ret;
        } else {
            return $this->callMethod($name, $args);
        }
    }

    private function callMethod($name, $args)
    {
        $try = 0;
        if (isset($this->params['_try'])) {
            // 处理尝试次数
            $try = intval($this->params['_try']);
            unset($this->params['_try']);
        }
        $method = $this->getMod() . '.' . $name;
        $url = '/_private/rpc/' . $method . '?_if=json&_of=json&_enc=0&_try=' . ($try + 1);
        if (isset($args[0])) {
            $input = array(
                'rpcinput' => $args
            );
        } else {
            $input = array(
                'rpcinput' => array()
            );
        }
        $input['rpcinit'] = $this->params;
        $ret = $this->srcCaller->rpcCall($url, $input);

        if (isset($ret['err'])) {
            if (($ret['err'] == 'ok' || $ret['err'] == 'hapn.ok') && isset($ret['data'])) {
                // 如果函数什么都不返回，rpcret就是null
                // 这时候isset判断会得到false
                return $ret['data']['rpcret'];
            } else {
                Logger::trace('apiproxy.httpjson.invalidret ' . var_export($ret, true));
                throw new Exception($ret['err']);
            }
        }
        throw new Exception('apiproxy.httpjson.invalidret ' . var_export($ret, true));
    }
}
