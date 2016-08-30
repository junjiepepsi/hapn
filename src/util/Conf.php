<?php
namespace hapn\util;

/**
 * Configure
 *
 * @author    : ronnie
 * @since     : 2016/7/7 23:06
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Conf.php
 */
class Conf
{
    private static $isLoaded = [];
    private static $confData = [];

    /**
     * Load the configure files
     *
     * @param array $paths
     */
    public static function load(array $paths)
    {
        foreach ($paths as $path) {
            if (isset(self::$isLoaded[$path])) {
                continue;
            }
            if (is_readable($path)) {
                require_once $path;
                self::$isLoaded[$path] = true;
            }
        }
    }

    /**
     * Get the configure data by key
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed|null
     */
    public static function get(string $key, $default = null)
    {
        if (array_key_exists($key, self::$confData)) {
            return self::$confData[$key];
        }
        return $default;
    }

    /**
     * Set the configure data by key
     *
     * @param string $key key of the configure
     * @param mixed  $value
     */
    public static function set(string $key, $value)
    {
        self::$confData[$key] = $value;
    }

    /**
     * Check if the key is defined
     *
     * @param string $key
     *
     * @return bool
     */
    public static function has(string $key)
    {
        return array_key_exists($key, self::$confData);
    }

    /**
     * Clear the configure data
     */
    public static function clear()
    {
        self::$isLoaded = array();
        self::$confData = array();
    }
}
