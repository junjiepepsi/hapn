<?php
namespace hapn\web\filter;

use hapn\web\Application;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/7/8 0:02
 * @copyright : 2016 jiehun.com.cn
 * @filesource: IFilter.php
 */
interface IFilter
{
    /**
     * Execute filter
     *
     * @param Application $app
     *
     * @return bool
     */
    public function execute(Application $app);
}
