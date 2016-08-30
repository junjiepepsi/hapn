<?php

namespace hapn\web\filter;

use hapn\util\Logger;
use hapn\web\Application;
use hapn\Exception;

/**
 *
 * Executor of filter
 *
 * @author    : ronnie
 * @since     : 2016/7/7 0:00
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Executor.php
 */
class Executor
{

    /**
     * @var IFilter[]
     */
    private $impFilters = [];
    /**
     * @var Application
     */
    private $app = null;

    /**
     * Executor constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Load filters
     *
     * @param $filters
     *
     * @throws Exception
     */
    public function loadFilters($filters)
    {
        foreach ($filters as $key => $classes) {
            foreach ($classes as $classname) {
                //Logger::debug('load filter %s.%s',$key,$classname);
                try {
                    $cls = new $classname();
                    if (!($cls instanceof IFilter)) {
                        throw new Exception('hapn.errinstance');
                    }
                    $this->impFilters[$key][] = $cls;
                } catch (\Exception $ex) {
                    throw new Exception('hapn.errclass');
                }
            }
        }
    }

    /**
     * Execute filter
     *
     * @param $filtername
     *
     * @return bool
     */
    public function execute($filtername)
    {
        if (!isset($this->impFilters[$filtername])) {
            //Logger::debug('miss filter %s',$filtername);
            return true;
        }
        $this->app->timer->begin('f_' . $filtername);
        $filters = $this->impFilters[$filtername];
        foreach ($filters as $filter) {
            if ($filter->execute($this->app) === false) {
                Logger::debug('call filter %s.%s=false', $filtername, get_class($filter));
                $this->app->timer->end('f_' . $filtername);
                return false;
            }
            //Logger::debug('call filter %s.%s=true',$filtername, get_class($filter));
        }
        $this->app->timer->end('f_' . $filtername);
        return true;
    }
}
