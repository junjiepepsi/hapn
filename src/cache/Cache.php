<?php
namespace hapn\cache;

use hapn\web\Application;
/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/8/30 21:03
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Cache.php
 */
class Cache
{
    private $_conf;
    private $_cache;
    /**
     * prefix of cache key
     * @var string
     */
    private $_prefix;
    /**
     * max length of key,if bigger than this value,shorten 32 by md5 method
     * @var int
     */
    private $_maxKeyLen = 64;

    public function __construct($conf)
    {
        $this->_conf = $conf;
        if (empty($this->_conf)) {
            throw new Exception('Cache.EmptyConf');
        }
        $this->_prefix = empty($this->_conf['key_prefix']) ? '' : $this->_conf['key_prefix'];
        // key最大长度
        if (isset($this->_conf['key_maxlen'])) {
            $this->_maxKeyLen = intval($this->_conf['key_maxlen']);
            if ($this->_maxKeyLen <= 32) {
                $this->_maxKeyLen = 32;
            }
        }

        if (!empty($this->_conf['client'])) {
            $client = ucfirst($this->_conf['client']);
        } else {
            $client = 'Memcached';
        }

        $className = __NAMESPACE__."\\".$client;
        $this->_cache = new $className($this->_conf);
    }

    private function statHapN()
    {
        if (isset($GLOBALS['__HapN_appid'])) {
            $GLOBALS['__HapN_appid']++;
        }
        global $__HapN_appid;
    }

    /**
     * 获取包装好的key
     * @param $key
     * @return string
     */
    private function getWrappedKey($key)
    {
        if (strlen($key) > $this->_maxKeyLen) {
            return $this->_prefix . md5($key);
        }
        return $this->_prefix . $key;
    }

    /**
     * 获取缓存
     *
     * @param string | array $key
     * @return mixed 如果返回null表示获取失败
     */
    function get($key)
    {
        $this->statHapN();
        if (is_string($key)) {
            $key = $this->getWrappedKey($key);
            return $this->_cache->get($key);
        } else {
            if (is_array($key)) {
                if ($this->_prefix) {
                    $oldKeys = $key;

                    foreach ($key as $k => $v) {
                        $key[$k] = $this->getWrappedKey($v);
                    }
                }
                $ret = $this->_cache->get_multi($key);
                if ($this->_prefix) {
                    foreach ($oldKeys as $k) {
                        $nKey = $this->getWrappedKey($k);
                        if (isset($ret[$nKey])) {
                            $ret[$k] = $ret[$nKey];
                            unset($ret[$nKey]);
                        } else {
                            $ret[$nKey] = null;
                        }
                    }
                }
                return $ret;
            }
        }
        return null;
    }

    /**
     * 设置缓存
     *
     * @param string $key
     * @param mixed $value
     * @param int $expire
     *
     * @return boolean | null 返回true表示设置成功
     */
    public function set($key, $value, $expire = 0)
    {
        $this->statHapN();
        $key = $this->getWrappedKey($key);
        return $this->_cache->set($key, $value, $expire);
    }

    /**
     * 批量设置缓存
     *
     * @param array $values
     * @param int $expire
     *
     * @return array
     * <code>array(
     *  $key => $ret // ret为true表示设置成功
     * )</code>
     */
    public function sets($values, $expire = 0)
    {
        $this->statHapN();
        if ($this->_prefix) {
            $oldKeys = array_keys($values);
            foreach ($values as $k => $v) {
                $values[$this->getWrappedKey($k)] = $v;
            }
        }
        if (!is_callable(array($this->_cache, 'set_multi'))) {
            $ret = array();
            foreach ($values as $k => $v) {
                $ret[$k] = $this->_cache->set($k, $v, $expire);
            }
            return $ret;
        }
        $ret = $this->_cache->set_multi($values, $expire);
        if (is_array($ret) && $this->_prefix) {
            foreach ($oldKeys as $k) {
                $nKey = $this->getWrappedKey($k);

                if (array_key_exists($nKey, $values)) {
                    $values[$k] = $values[$nKey];
                    unset($values[$nKey]);
                } else {
                    $values[$k] = false;
                }
            }
        }
        return $values;
    }

    /**
     * 删除指定的缓存
     *
     * @param string $key
     * @param int $time
     *
     * @return boolean 返回true表示删除成功
     */
    public function del($key, $time = 0)
    {
        $this->statHapN();
        $key = $this->getWrappedKey($key);
        $this->_cache->delete($key, $time);
    }

    /**
     * 递增
     *
     * @param string $key
     * @param int $count
     *
     * @return int 新的值
     */
    public function incr($key, $count = 1)
    {
        $this->statHapN();
        $key = $this->getWrappedKey($key);
        return $this->_cache->incr($key, $count);
    }

    /**
     * 递减
     *
     * @param string $key
     * @param int $count
     *
     * @return int 新的值
     */
    public function decr($key, $count = 1)
    {
        $this->statHapN();
        $key = $this->getWrappedKey($key);
        return $this->_cache->decr($key, $count);
    }

    public function close()
    {
        return $this->_cache->close();
    }
}