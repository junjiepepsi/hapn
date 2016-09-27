<?php
namespace hapn\swoole;

/**
 * 控制器
 *
 * @author    : ronnie
 * @since     : 2016/9/23 15:59
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Controller.php
 */
class Controller
{
    /**
     * @var \swoole_http_server
     */
    private $server;

    public $uri;
    public $callback = '';

    public function __construct(\swoole_http_server $serv)
    {
        $this->server = $serv;
    }

    /**
     * 增加任务
     *
     * @param string $name 任务名称
     * @param array $params 任务参数
     * @param $callback 回调地址
     * @return int 任务ID
     */
    public function task(string $name, array $params, $callback = null)
    {
        if ($callback === null) {
            $callback = $this->callback;
        }

        $taskData = [$name, $params, $callback];
        return $this->server->task(json_encode($taskData, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 记录日志
     * @param $msg
     */
    public function log($msg)
    {
        if (!is_scalar($msg)) {
            $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
        }
        Application::log('[CONTROLLER][uri:' . $this->uri . '] ' . $msg);
    }
}
