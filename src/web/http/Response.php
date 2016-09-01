<?php
namespace hapn\web\http;

use hapn\util\Conf;
use hapn\util\Logger;
use hapn\util\Xml;
use hapn\web\Application;
use hapn\Exception;
use hapn\web\view\IView;

/**
 * 响应类
 *
 * @author    : ronnie
 * @since     : 2016/6/26 12:24
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Request.php
 */
class Response
{
    /**
     * @var Application
     */
    private $app;

    const STATUS_OK = 'hapn.ok';
    const STATUS_FATAL = 'hapn.fatal';
    const STATUS_INPUT = 'hapn.u_input';

    /**
     * Output variables
     *
     * @var array
     */
    public $outputs = [];

    public $headers = [];
    /**
     * Template's path
     *
     * @var string
     */
    private $template;
    private $layouts = [];
    private $raw;
    /**
     * @var Exception
     */
    private $exception;
    private $error;
    private $needSetContentType = true;
    private $callback;
    private $results = [];
    private $cookies = [];
    /**
     * background task.
     * @var array
     */
    private $tasks = [];

    /**
     * Response constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Reset
     */
    public function reset()
    {
        $this->headers = [];
        $this->cookies = [];
        $this->exception = null;
        $this->error = null;
        $this->needSetContentType = true;
        $this->template = '';
        $this->layouts = [];
        $this->tasks = [];
        $this->raw = null;
        $this->callback = null;
        $this->outputs = [];
        $this->results = [];
    }

    /**
     * Set exception
     *
     * @param $ex Exception
     *
     * @return Response
     */
    public function setException($ex) : Response
    {
        $this->exception = $ex;
        return $this;
    }

    /**
     * Set error
     *
     * @param $err
     *
     * @return Response
     */
    public function setError($err) : Response
    {
        $this->error = $err;
        return $this;
    }

    /**
     * Set a variable
     *
     * @param string $key The key of output variable
     * @param mixed $value The value of output variable
     *
     * @return Response
     */
    public function set(string $key, $value) : Response
    {
        $this->outputs[$key] = $value;
        return $this;
    }

    /**
     * Set multi variables
     *
     * @param array $vars variables
     *
     * @return Response
     */
    public function sets(array $vars) : Response
    {
        foreach ($vars as $k => $v) {
            $this->outputs[$k] = $v;
        }
        return $this;
    }

    /**
     * Set the callback function's name of jsonp
     *
     * @param string $funcName
     *
     * @return Response
     * @throws Exception hapn.errcallback illegal function name
     */
    public function setCallback($funcName) : Response
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z_0-9.]{0,128}$/', $funcName)) {
            throw new Exception('hapn.errcallback');
        }
        $this->callback = $funcName;

        // Change the encoding and format
        $this->app->request->of = Application::FORMAT_JSON;
        $this->app->request->oe = Application::ENCODING_UTF8;
        return $this;
    }


    /**
     * Set template
     *
     * @param string $tpl Path of template
     *
     * @return Response
     */
    public function setView(string $tpl) : Response
    {
        $this->template = $this->app->getDir('view') . '/' . ltrim($tpl, '/');
        $this->app->request->of = Application::FORMAT_HTML;
        return $this;
    }

    /**
     * Set output header
     *
     * @param string|array $key key
     * @param null $value value
     *
     * @return Response
     */
    public function setHeader(string $key, $value = null) : Response
    {
        if ($this->needSetContentType) {
            if (($value === null && strpos(strtolower($key), 'content-type:') === 0)
                || ($value !== null && strtolower($key) == 'content-type')
            ) {
                $this->needSetContentType = false;
            }
        }
        if ($value !== null) {
            $this->headers[] = $key . ': ' . $value;
        } else {
            $this->headers[] = $key;
        }
        return $this;
    }

    /**
     * Set layout
     *
     * @param string|array $layout layout
     *
     * @return Response
     */
    public function setLayout($layout) : Response
    {
        if (is_array($layout)) {
            foreach ($layout as $k => $v) {
                $layout[$k] = $this->app->getDir('view') . '/' . ltrim($v, '/');
            }
            $this->layouts = $layout;
        } else {
            $this->layouts = [$this->app->getDir('view') . '/' . ltrim($layout, '/')];
        }
        return $this;
    }


    /**
     * Defines a cookie to be sent along with the rest of the HTTP headers
     *
     * @param string $name The name of the cookie
     * @param string $value The value of the cookie
     * @param int $expires The time the cookie expires
     * @param string $path The path on the server in which the cookie will be available on
     * @param string $domain The (sub)domain that the cookie is available to
     * @param bool $secure Indicates that the cookie should only be transmitted over a secure HTTPS connection from
     *                         the client
     * @param bool $httponly When TRUE the cookie will be made accessible only through the HTTP protocol
     *
     * @return Response
     */
    public function setCookie(
        string $name,
        $value = '',
        int $expires = null,
        $path = '/',
        $domain = '',
        bool $httponly = false,
        bool $secure = false
    ) {
        $this->cookies[] = func_get_args();
        return $this;
    }

    /**
     * 设置原始输出内容
     *
     * @param $raw
     *
     * @return Response
     */
    public function setRaw($raw) : Response
    {
        $this->raw = $raw;
        return $this;
    }

    /**
     * Redirect
     *
     * @param      $url
     * @param bool $permanent
     */
    public function redirect($url, $permanent = false)
    {
        if ($permanent) {
            $this->setHeader('HTTP/1.1 301 Moved Permanently');
        }
        $this->setHeader('Location', $url);
        $this->app->endStatus = Application::ENDSTATUS_OK;
        $this->sendHeaders();
        exit();
    }

    /**
     * Set the header of framework
     *
     * @param string $errCode
     *
     * @return Response
     */
    public function setFrHeader($errCode = 'suc') : Response
    {
        $header = sprintf('hapn: id=%d', $this->app->appId);

        if ($errCode != 'suc') {
            $method = 'r';
            $urls = explode('/', $this->app->request->url);
            if ($urls && strncmp($urls[count($urls) - 1], '_', 1) === 0) {
                // whether the last segment begin with '_'
                $method = 'w';
            }
            $header .= sprintf(',e=%s,m=%s', $errCode, $method);
            if (($retry = $this->app->request->get('retry'))) {
                $header .= ',r=' . intval($retry);
            }
        }

        $this->setHeader($header);
        return $this;
    }

    /**
     * Tell brower or delagate not cache this page
     *
     * @return Response
     */
    public function setNoCache() : Response
    {
        $this->setHeader('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
        $this->setHeader('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
        $this->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $this->setHeader('Pragma', 'no-cache');

        return $this;
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
        return call_user_func_array([$this->request, 'gets'], $keys);
    }

    /**
     * Set the variable of framework
     *
     * @param string $key Key of the variable
     * @param mixed $value Value of the variable
     *
     * @return Response
     */
    public function setFrVar(string $key, $value) : Response
    {
        $this->results[$key] = $value;
        return $this;
    }

    /**
     * Send the http headers
     *
     * @return Response
     */
    public function sendHeaders() : Response
    {
        foreach ($this->cookies as $cookie) {
            call_user_func_array('setcookie', $cookie);
        }
        foreach ($this->headers as $header) {
            header($header);
        }
        return $this;
    }

    /**
     * End the output
     */
    public function end()
    {
        $this->app->filter(Application::FILTER_OUTPUT);
        //Set the endstatus
        $this->app->endStatus = Application::ENDSTATUS_OK;
        exit();
    }

    /**
     * Run tasks
     */
    private function runTasks()
    {
        // // Check task
        if (!empty($this->tasks)) {
            fastcgi_finish_request();

            // allow to run one hour
            ini_set('max_execution_time', intval(Conf::get('hapn.fastcgi_finish_timeout', 3600)));

            foreach ($this->tasks as $task) {
                if (is_callable($task[0])) {
                    // buffer output
                    ob_start();
                    call_user_func_array($task[0], $task[1]);
                    $output = ob_get_clean();
                    if ($output) {
                        Logger::trace('task output:' . $output);
                    }
                }
            }
        }
    }

    /**
     * Build the header named "Content-Type"
     *
     * @param $of
     * @param $encoding
     */
    private function buildContentType($of, $encoding)
    {
        if (!$this->needSetContentType) {
            return;
        }
        switch ($of) {
            case 'json':
                $this->headers[] = 'Content-type: application/json; charset=' . $encoding;
                break;
            case 'html':
                $this->headers[] = 'Content-Type: text/html; charset=' . $encoding;
                break;
            case 'xml':
                $this->headers[] = 'Content-Type: text/xml; charset=' . $encoding;
                break;
            case 'jpg':
            case 'png':
            case 'gif':
                $this->headers[] = 'Content-Type: image/' . $of;
                break;
            default:
                $this->headers[] = 'Content-Type: text/plain; charset=' . $encoding;
        }

        $this->needSetContentType = false;
    }

    /**
     * Fetch the result of compiled template
     *
     * @param string $template path of template
     * @param array $userData variables of template
     * @param boolean $output whether output
     *
     * @throws Exception hapn.errclass template class not found
     * @return string|void
     */
    public function buildView($template, array $userData, $output = false)
    {
        $engine = Conf::get('hapn.view', "\\hapn\\web\\view\\PhpView");
        $engineName = substr($engine, strripos($engine, "\\") + 1);
        $view = new $engine();
        if (!($view instanceof IView)) {
            throw new Exception('view.notImplementOfIview');
        }
        $view->init($this->app);
        $view->setArray($userData);
        if (!$output) {
            $this->app->timer->begin($engineName);
            $result = $view->build($template);
            $this->app->timer->end($engineName);
            return $result;
        }
        if ($this->layouts) {
            if (is_array($this->layouts)) {
                call_user_func_array(array($view, 'setLayout'), $this->layouts);
            } else {
                $view->setLayout($this->layouts);
            }
        }
        $this->app->timer->begin($engineName);
        $view->display($template);
        $this->app->timer->end($engineName);
        return null;
    }

    /**
     * Get the result of output
     *
     * @return array
     */
    private function getResult() : array
    {
        if ($this->exception) {
            $errcode = $this->exception->getMessage();
            if (($pos = strpos($errcode, ' '))) {
                $errcode = substr($errcode, 0, $pos);
            }
            if (!preg_match('/^[a-zA-Z0-9\.\-_]{1,50}$/', $errcode)) {
                //普通的错误信息不能传到前端
                $errcode = self::STATUS_FATAL;
            }
            $result = array('err' => $errcode);
            if ($errcode == self::STATUS_INPUT) {
                //输入check错误时，可以带些数据
                $result['data'] = $this->outputs;
                Logger::debug('input data error:%s', print_r($result['data'], true));
            }
        } elseif ($this->error) {
            $result = array(
                'err' => self::STATUS_FATAL
            );
        } else {
            $result = array(
                'err' => self::STATUS_OK,
                'data' => $this->outputs
            );
        }
        foreach ($this->results as $key => $value) {
            if (!isset($result[$key])) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Format output
     *
     * @return mixed|string
     */
    protected function formatResponse()
    {
        $result = $this->getResult();
        $of = $this->app->request->of;
        $this->buildContentType($of, $this->app->request->oe);
        if (!is_null($this->raw)) {
            return $this->raw;
        } elseif ($this->template) {
            return $this->buildView($this->template, $this->outputs, true);
        } else {
            //formatter
            if ($of == Application::FORMAT_JSON) {
                if ($this->callback) {
                    return $this->callback . '(' . json_encode($result, JSON_UNESCAPED_UNICODE) . ')';
                } else {
                    $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
                    if ($this->app->request->pretty) {
                        $options |= JSON_PRETTY_PRINT;
                    }
                    return json_encode($result, $options);
                }
            } elseif ($of == Application::FORMAT_XML) {
                return Xml::array2Xml($result, $this->app->request->oe);
            } else {
                return print_r($result, true);
            }
        }
    }

    /**
     * Send output
     */
    public function send()
    {
        $this->setFrHeader();
        $data = $this->formatResponse();
        $this->sendHeaders();

        // fetch the output data
        $ob = ini_get('output_buffering');
        if ($ob && strtolower($ob) !== 'off') {
            $str = ob_get_clean();
            //trim data
            $data = trim($str) . $data;
        }

        if ($data) {
            $outhandler = Conf::get('hapn.outputhandler', array());
            if ($outhandler && !is_array($outhandler)) {
                // support string
                $outhandler = array($outhandler);
            }
            if ($outhandler) {
                // call the global output handlers
                foreach ($outhandler as $handler) {
                    if (is_callable($handler)) {
                        $data = call_user_func($handler, $data);
                    } else {
                        Logger::warn(
                            "outouthandler:%s can't call",
                            is_array($handler) ? $handler[1] : $handler
                        );
                    }
                }
            }
            echo $data;
        }

        $this->runTasks();
    }

    /**
     * Add background task
     * @param callable $task
     * @param array $args
     * @return $this
     */
    public function addTask($task, $args = [])
    {
        $this->tasks[] = [$task, $args];
        return $this;
    }

    /**
     * Set a provider
     * @param string $name the name for provider's data
     * @param string $provider
     * @param array $params the params for provider's provider method(static)
     * @throws Exception response.providerRequired
     * @return $this
     */
    public function setProvider($name, string $provider, $params = [])
    {
        if (!$name) {
            throw new Exception('response.providerRequired');
        }
        $this->providers[] = [$name, $provider, $params];
        return $this;
    }
}
