<?php
namespace hapn\web\filter;

use hapn\util\Encoding;
use hapn\web\Application;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/7/15 0:20
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Output.php
 */
class Output implements IFilter
{
    /**
     * Execute filter
     *
     * @param Application $app
     *
     * @return bool
     */
    public function execute(Application $app)
    {
        $to = $app->request->outputEncoding;
        if ($app->encoding !== $to) {
            $app->response->outputs = $this->transEncoding($app->response->outputs, $to, $app->encoding);
            $app->response->headers = $this->transEncoding($app->response->headers, $to, $app->encoding);
        }
        $app->response->send();
        return true;
    }

    /**
     * Transform encoding
     *
     * @param $arr
     * @param $to
     * @param $from
     *
     * @return array
     */
    private function transEncoding($arr, $to, $from)
    {
        $ret = array();
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                $value = $this->transEncoding($value, $to, $from);
            } else {
                $value = Encoding::convert($value, $to, $from);
            }
            if (is_string($key)) {
                $key = Encoding::convert($key, $to, $from);
            }
            $ret[$key] = $value;
        }
        return $ret;
    }
}
