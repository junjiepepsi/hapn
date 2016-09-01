<?php
namespace hapn\web\view;

use hapn\web\Application;

/**
 * Interface of view
 *
 * @author    : ronnie
 * @since     : 2016/7/7 22:29
 * @copyright : 2016 jiehun.com.cn
 * @filesource: IView.php
 */
interface IView
{
    /**
     * Init View
     * @param array $conf
     *
     * @return mixed
     */
    public function init(array $conf = []);

    /**
     * Set variable for template
     *
     * @param string $name Name of the variable
     * @param  mixed $value Value of the variable
     *
     * @return mixed
     */
    public function set(string $name, $value);

    /**
     * Set variables for template
     *
     * @param array $vars
     *
     * @return mixed
     */
    public function setArray(array $vars);

    /**
     * Output the result of compiled template
     *
     * @param string $tpl
     *
     * @return mixed
     */
    public function display(string $tpl);

    /**
     * Fetch the result of compiled template
     *
     * @param string $tpl
     *
     * @return string
     */
    public function build(string $tpl) : string;

    /**
     * Set layout
     *
     * @param string[] $layouts
     *
     * @return void
     */
    public function setLayout(string ...$layouts);

    /**
     * Fetch the main body's content, used by the layout
     *
     * @return string
     */
    public function getBody();

    /**
     * Contain a child template
     *
     * @param string $tpl
     * @param array $vars
     *
     * @return string
     */
    public function partial(string $tpl, array $vars = []);
}
