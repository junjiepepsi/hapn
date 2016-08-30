<?php
namespace hapn\web\view;

/**
 *
 * @author:     ronnie
 * @since:      16/8/23 上午12:45
 * @copyright:  2016 jiehun.com.cn
 * @filesource: ViewHelper.php
 */
;

abstract class ViewHelper
{
    /**
     * @var \firegit\http\View
     */
    var $view;

    public function __construct($view)
    {
        $this->view = $view;
    }
}