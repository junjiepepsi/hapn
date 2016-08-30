<?php
namespace hapn\web\http;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/7/15 23:13
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Controller.php
 */
class Controller
{
    /**
     * Http request object
     *
     * @var Request
     */
    public $request;

    /**
     * Http response object
     *
     * @var Response
     */
    public $response;

    /**
     * debug mode
     *
     * @var bool
     */
    public $debug;

    /**
     * encoding
     *
     * @var string
     */
    public $encoding;


    /**
     * Method name
     * @var string
     */
    public $method;

    /**
     * Arguments of request
     * @var array
     */
    public $args;

    public function _before()
    {
    }

    public function _after()
    {
    }

    /**
     * Fetch request datas
     *
     * @param \string[] $keys
     *
     * @return array
     * @see Request::gets
     */
    public function gets(string ...$keys)
    {
        return call_user_func_array([$this->request, 'gets'], $keys);
    }

    /**
     * Fetch request data
     *
     * @param string $key
     * @param null $default
     *
     * @return mixed|null
     * @see Request::get
     */
    public function get(string $key, $default = null)
    {
        return $this->request->get($key, $default);
    }

    /**
     * Set response data
     *
     * @param string $key
     * @param        $value
     *
     * @return $this
     * @see Response::set
     */
    public function set($key, $value = null)
    {
        if (is_array($key)) {
            $this->response->sets($key);
            return $this;
        }
        $this->response->set($key, $value);
        return $this;
    }

    /**
     * Set response datas
     *
     * @param \string[] ...$vars
     *
     * @see Response::sets
     */
    public function sets($vars)
    {
        $this->response->sets($vars);
        return $this;
    }

    /**
     * @param string $tpl
     *
     * @return $this
     * @see Response::setView
     */
    public function setView(string $tpl)
    {
        $this->response->setView($tpl);
        return $this;
    }

    /**
     * Fetch user data by key
     *
     * @param string $key
     * @param null $default
     * @return mixed
     *
     * @see Request::getData
     */
    public function getData(string $key, $default = null)
    {
        return $this->request->getData($key, $default);
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
        $this->request->setData($key, $value);
        return $this;
    }

    /**
     * Set layout
     *
     * @param string|array $layout layout
     *
     * @return $this
     */
    public function setLayout($layout)
    {
        $this->response->setLayout($layout);
        return $this;
    }
}
