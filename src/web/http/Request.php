<?php
namespace hapn\web\http;

use hapn\Exception;
use hapn\web\Application;

/**
 * Http Request
 *
 * @author    : ronnie
 * @since     : 2016/6/26 12:24
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Request.php
 */
class Request
{
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_DELETE = 'DELETE';
    const METHOD_HEAD = 'HEAD';
    const METHOD_OPTIONS = 'OPTIONS';

    /**
     * Supported request methods
     *
     * @var string[]
     */
    private static $avalableMethods = array(
        self::METHOD_GET,
        self::METHOD_POST,
        self::METHOD_PUT,
        self::METHOD_DELETE,
        self::METHOD_HEAD,
        self::METHOD_OPTIONS
    );

    /**
     * The format of response's body
     *
     * @var string
     */
    public $of = Application::FORMAT_DEFAULT;

    /**
     * The encoding of response's body
     *
     * @var string
     */
    public $oe = Application::ENCODING_UTF8;

    /**
     * The format of requrest's body
     *
     * @var string
     */
    public $if;

    /**
     * The encoding of requrest's body
     *
     * @var string
     */
    public $ie = Application::ENCODING_UTF8;

    /**
     * Whether output the pretty data
     *
     * @var bool
     */
    public $pretty = false;

    /**
     * Request uri, with query string
     *
     * @var string
     */
    public $uri;

    /**
     * Request url, without query string
     *
     * @var string
     */
    public $url;

    /**
     * Raw request uri
     *
     * @var string
     */
    public $rawUri;

    /**
     * Request host
     *
     * @var string
     */
    public $host;

    /**
     * Path's prefix
     * @var string
     */
    public $prefix;

    /**
     * Request method such as GET POST PUT DELETE etc.
     *
     * @var string
     */
    public $method;

    /**
     * Whether redirect to the error page
     *
     * @var bool
     */
    public $errorPage = false;
    /**
     * Privious request node's ip
     *
     * @var string
     */
    public $clientip;
    /**
     * The real ip of request user
     *
     * @var
     */
    public $userip;

    /**
     * Server's variables
     *
     * @var array
     */
    public $serverEnvs = [];

    /**
     * Whether a XMLHttpRequest
     *
     * @var bool
     */
    public $isAjax = false;

    /**
     * User's data
     *
     * @var array
     */
    public $userData = [];

    /**
     * Application's Request ID
     *
     * @var int
     */
    public $appId = 0;

    /**
     * Request inputs
     *
     * @var array
     */
    public $inputs = [];

    /**
     * Formated $_COOKIE
     *
     * @var array
     */
    public $cookies = [];

    /**
     * Application
     *
     * @var Application
     */
    private $app;

    /**
     * Request time
     *
     * @var int
     */
    public $now;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->host = strtolower(
            isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : Conf::get('hapn.host', '')
        );
        $this->rawUri = $_SERVER['REQUEST_URI'];
        $this->method = $_SERVER['REQUEST_METHOD'];
        if (!in_array($this->method, self::$avalableMethods)) {
            throw new Exception('hapn.reqMethodForbidden');
        }
    }

    /**
     * Reset
     */
    public function reset()
    {
        $this->app = null;
        $this->inputs = [];
        $this->userData = [];
        $this->cookies = [];
        $this->pretty = false;
    }

    /**
     * Fetch the $_GET data by key
     *
     * @param  string $key Name of the param
     * @param null $default
     *
     * @return mixed|null
     */
    public function get(string $key, $default = null)
    {
        if (isset($this->inputs[$key])) {
            return $this->inputs[$key];
        }
        return $default;
    }

    /**
     * Fetch a lot of $_GET data
     *
     * @param \string[] ...$keys
     *
     * @return array
     */
    public function gets(string ...$keys)
    {
        $ret = [];
        foreach ($keys as $key) {
            if (isset($this->inputs[$key])) {
                $ret[$key] = $this->inputs[$key];
            } else {
                $ret[$key] = null;
            }
        }
        return $ret;
    }


    /**
     * Set user data by key
     *
     * @param string|array $key
     * @param        $value
     * @return $this
     */
    public function setData($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->userData[$k] = $v;
            }
            return;
        }
        $this->userData[$key] = $value;
        return $this;
    }

    /**
     * Fetch user data by key
     *
     * @param string $key
     * @param null $default
     *
     * @return mixed|null
     */
    public function getData(string $key, $default = null)
    {
        if (isset($this->userData[$key])) {
            return $this->userData[$key];
        }
        return $default;
    }

    /**
     * Fetch the cookie by key
     *
     * @param string $key key of cookie
     * @param string $default default value
     *
     * @return string
     */
    public function getCookie($key, $default = null)
    {
        if (isset($this->cookies[$key])) {
            return $this->cookies[$key];
        }
        return $default;
    }

    /**
     * Is need to redirect to the error page
     * When request method is GET and output format is html or default,
     * or _e=1, it will redirect to the error page
     *
     * @return bool
     */
    public function needErrorPage()
    {
        if ($this->errorPage) {
            return true;
        }
        if ($this->method === self::METHOD_GET &&
            ($this->of == Application::FORMAT_HTML || $this->of == Application::FORMAT_DEFAULT)
        ) {
            return true;
        }
        return false;
    }

    /**
     * Get the header value,it will become uppercase, and add the prefix "HTTP_'
     *
     * @param string $key key of the header
     * @param string $default default value
     *
     * @return string
     */
    public function getHeader($key, $default = null)
    {
        $name = 'HTTP_' . strtoupper($key);
        if (isset($this->serverEnvs[$name])) {
            return $this->serverEnvs[$name];
        }
        return $default;
    }

    /**
     * Fetch the upload file
     *
     * @param string $key 名称
     *
     * @return array | null
     */
    public function getFile($key)
    {
        if (isset($_FILES[$key])) {
            return $_FILES[$key];
        }
        return null;
    }
}
