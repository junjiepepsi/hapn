<?php
namespace hapn\curl;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/7/9 13:24
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Response.php
 */
class Response
{
    /**
     * 状态码
     * @var int
     */
    public $code;

    /**
     * 状态描述
     * @var string
     */
    public $status;

    /**
     * cookie
     * @var array
     */
    public $cookie;

    /**
     * header
     * @var array
     */
    public $header;

    /**
     * html代码
     * @var string
     */
    public $content;

    /**
     * 协议名称
     * @var string
     */
    public $protocol;
}
