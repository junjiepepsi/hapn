<?php
namespace hapn\swoole;

/**
 * 任务类
 *
 * @author    : ronnie
 * @since     : 2016/9/23 16:01
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Task.php
 */
class Task
{
    public $taskId;
    public $uri;

    public function __construct($taskId)
    {
        $this->taskId = $taskId;
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
        Application::log('[TASK][uri:' . $this->uri . '][taskid:' . $this->taskId . '] ' . $msg);
    }
}
