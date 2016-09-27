<?php
namespace hapn\console;

use hapn\curl\Request;
use hapn\Exception;
use hapn\util\Logger;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/9/26 18:02
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Crontab.php
 */
class Crontab
{
    private static $tasks = [];

    // 没有运行
    const STATUS_NOT_RUN = 0;
    // 正在运行
    const STATUS_RUNNING = 2;
    // 运行成功
    const STATUS_OK = 4;
    // 运行失败
    const STATUS_ERROR = 8;

    /**
     * 添加定时任务
     * @param string $key
     * @param int $interval
     * @param string $url
     * @param array $args
     * @throws Exception
     */
    public static function addTask($key, $interval, $url, $args = null)
    {
        if (isset(self::$tasks[$key])) {
            if (!self::$tasks[$key]['enable']) {
                unset(self::$tasks[$key]);
            } else {
                throw new Exception('task.keyExist key=' . $key);
            }
        }

        self::$tasks[$key] = [
            'url' => $url,
            'args' => $args,
            'interval' => $interval,
            'nextTime' => time() + $interval,
            'pid' => 0,
            'status' => self::STATUS_NOT_RUN,
            'startTime' => 0,
            'endTime' => 0,
            'enable' => true,
            'runTime' => 0,
            'error' => '',
        ];
    }

    /**
     * 删除定时任务
     * @param $key
     */
    public static function removeTask($key)
    {
        if (!isset(self::$tasks[$key])) {
            return;
        }
        self::$tasks[$key]['enable'] = false;
    }

    /**
     * 获取任务
     */
    public static function getTasks()
    {
        return self::$tasks;
    }

    /**
     * 简单实现了一个单进程的定时任务
     */
    public static function run()
    {
        while(true) {
            foreach(self::$tasks as $key => &$task) {
                $now = time();
                if ($task['enable'] && $task['status'] != self::STATUS_RUNNING && $task['nextTime'] <= $now) {
                    ob_get_clean();

                    Logger::addBasic(['task' => $key]);
                    Logger::trace('task start');

                    $task['status'] = self::STATUS_RUNNING;
                    $task['runTime']++;
                    $task['startTime'] = $now;
                    $task['error'] = '';

                    try{
                        $req = new Request();
                        if ($task['args'] === null) {
                            $ret = $req->get($task['url']);
                        } else {
                            $ret = $req->post($task['url'], $task['args']);
                        }
                        Logger::trace('curl:'.$task['url'].';args:'.var_export($task['args'], true));

                        if ($ret->code != 200) {
                            $task['status'] = self::STATUS_ERROR;
                            $task['error'] = $ret->body;
                        } else {
                            $task['status'] = self::STATUS_OK;
                        }
                    } catch (\Exception $ex) {
                        $task['status'] = self::STATUS_ERROR;
                        $task['error'] = $ex->getMessage();
                        Logger::warn('curl faild:'.$ex->getTraceAsString());
                    }

                    $now = time();
                    $task['endTime'] = $now;
                    Logger::trace('task:'.var_export($task, true));

                    $task['nextTime'] = $now + $task['interval'];
                }
                unset($task);
            }
            sleep(1);
        }
    }
}
