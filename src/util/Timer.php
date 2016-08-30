<?php
namespace hapn\util;

/**
 *
 * Timer
 *
 * @author    : ronnie
 * @since     : 2016/7/7 22:05
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Timer.php
 */
final class Timer
{
    private $start = 0;
    private $stats = [];

    /**
     * Timer constructor.
     */
    public function __construct()
    {
        $this->start = microtime(true);
    }

    /**
     * Begin a timer segment.The last timer will be terminated
     *
     * @param string[] $phases the name of the timers
     *
     * @access public
     * @return float current timestamp(unit:10^-3ms)
     */
    public function begin(...$phases)
    {
        $now = microtime(true);
        foreach ($phases as $phase) {
            if (!isset($this->stats[$phase])) {
                //array($now,$end);
                $this->stats[$phase] = [$now, 0];
            }
        }
        return $now;
    }

    /**
     * Terminate the timers
     *
     * @param string[] $phases the names of timer that will be terminated
     *
     * @return float current timestamp
     */
    public function end(...$phases)
    {
        $now = microtime(true);
        foreach ($phases as $phase) {
            if (isset($this->stats[$phase])) {
                $this->stats[$phase][1] = $now;
            }
        }
        return $now;
    }

    /**
     * Terminate all timers
     *
     * @return float current timestamp
     */
    public function endAll()
    {
        $now = microtime(true);
        foreach ($this->stats as $phase => &$stat) {
            if ($stat[1] === 0) {
                $stat[1] = $now;
            }
        }
        return $now;
    }

    /**
     * Get the result of all timers
     *
     * @param bool $end if stop all timers
     *
     * @return array array(
     *  $phase => $cost
     * )
     */
    public function getResult($end = true)
    {
        if ($end) {
            $this->endAll();
        }
        $result = [];
        foreach ($this->stats as $phase => $stat) {
            if ($stat[1]) {
                $result[$phase] = intval(($stat[1] - $stat[0]) * 1000000);
            }
        }
        return $result;
    }
}
