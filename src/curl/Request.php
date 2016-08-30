<?php
namespace hapn\curl;

use hapn\Exception;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/7/9 13:24
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Request.php
 */
class Request
{
    private $options;

    private static $defaultOpts = [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_HEADER => 1,
        CURLOPT_FOLLOWLOCATION => 3,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'hapn Curl',
        CURLOPT_AUTOREFERER => 1,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_MAXREDIRS => 3,
        //CURLOPT_VERBOSE => true
    ];
    private static $fixedOpts = [CURLOPT_RETURNTRANSFER, CURLOPT_HEADER];

    /**
     * Request constructor.
     *
     * @param array $confs
     */
    public function __construct(array $confs = [])
    {
        $this->options = self::$defaultOpts;

        if ($confs) {
            foreach ($confs as $key => $value) {
                $this->options[$key] = $value;
            }
        }
    }
    
    /**
     * Get options
     *
     * @param $opt
     *
     * @return array
     */
    private function getOptions($opt)
    {
        foreach (self::$fixedOpts as $key) {
            unset($opt[$key]);
        }
        //数字下标的array不能merge，否则下标会从0开始计
        $ret = self::$defaultOpts;
        foreach ($opt as $key => $value) {
            $ret[$key] = $value;
        }
        return $ret;
    }

    /**
     * Do real request
     *
     * @param string  $url
     * @param array   $postData
     * @param array   $opt
     * @param boolean $buildQuery
     *
     * @return Response
     * @throws Exception
     */
    private function doReq($url, $postData, $opt, $buildQuery)
    {
        if ($postData) {
            if ($buildQuery) {
                $opt[CURLOPT_POSTFIELDS] = http_build_query($postData);
            } else {
                $opt[CURLOPT_POSTFIELDS] = $postData;
            }
        }

        $opts = $this->getOptions($opt);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, $opts);
        $data = curl_exec($ch);
        if ($data === false) {
            $info = curl_getinfo($ch);
            if ($info['http_code'] == 301 ||
                $info['http_code'] == 302
            ) {
                throw new Exception('mcutil.curlerr redirect occurred:' . $info['url']);
            }
        }
        $err = curl_errno($ch);
        $errmsg = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception('mcutil.curlerr ' . $errmsg);
        }
        return $this->parse($data);
    }

    /**
     * Send a GET request
     *
     * @param string $url
     * @param array  $opt
     * @param string $postData
     * @param bool   $buildQuery
     *
     * @return Response
     */
    public function get($url, $opt = [], $postData = null, $buildQuery = false)
    {
        $opt[CURLOPT_HTTPGET] = true;
        return $this->doReq($url, $postData, $opt, $buildQuery);
    }


    /**
     * Send a GET request
     *
     * @param string  $url
     * @param array   $postData
     * @param array   $opt
     * @param boolean $buildQuery
     *
     * @return Response
     */
    public function post($url, $postData = [], $opt = [], $buildQuery = true)
    {
        $opt[CURLOPT_POST] = true;
        return $this->doReq($url, $postData, $opt, $buildQuery);
    }

    /**
     * Send a upload file request
     *
     * @param string $url
     * @param array  $postData
     * @param array  $postFile
     * [
     *   $key => [
     *     'file' => '', // filename
     *     'type' => '', // file type
     *     'blob' => '', // binary data
     *   ]
     * ]
     * @param array  $opt
     *
     * @return Response
     * @throws Exception
     */
    public function postFile($url, array $postData, array $postFile, $opt = [])
    {
        $opt[CURLOPT_POST] = true;
        if (!empty($postFile)) {
            foreach ($postFile as $key => $data) {
                if (!is_array($data)) {
                    throw new Exception('curl.postFileIllegal');
                }
                $key = "{$key}\"; filename=\"{$data['name']}\r\nContent-Type: {$data['type']}\r\n";
                $postData[$key] = $data['blob'];
            }
        }
        return $this->doReq($url, $postData, $opt, false);
    }

    /**
     * Send a PUT request
     *
     * @param string      $url
     * @param array       $postData
     * @param array       $opt
     * @param bool|string $buildQuery
     *
     * @return Response
     */
    public function put($url, $postData = [], $opt = [], $buildQuery = true)
    {
        $opt[CURLOPT_CUSTOMREQUEST] = 'PUT';
        return $this->post($url, $postData, $opt, $buildQuery);
    }

    /**
     * Send a DELETE request
     *
     * @param string      $url
     * @param array       $postData
     * @param array       $opt
     * @param bool|string $buildQuery
     *
     * @return Response
     */
    public function delete($url, $postData = [], $opt = [], $buildQuery = true)
    {
        $opt[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        return $this->post($url, $postData, $opt, $buildQuery);
    }

    /**
     * 解析返回内容
     *
     * @param string $ret
     *
     * @return Response
     * @throws Exception
     */
    private function parse($ret)
    {
        $pos = false;
        $header = '';
        while (true) {
            $pos = strpos($ret, "\r\n\r\n");
            if (!$pos) {
                throw new Exception('mcutil.curlerr redirect occurred:' . $ret);
            }
            $header = substr($ret, 0, $pos);
            // check the status whether is 10x
            list($_proto, $_status) = explode(' ', $header);

            if ($_status >= 100 && $_status < 200) {
                $ret = substr($ret, $pos + 4);
                continue;
            }
            break;
        }
        $body = substr($ret, $pos + 4);
        $headerLines = explode("\r\n", $header);
        $head = array_shift($headerLines);
        $cookies = [];
        $headers = [];
        $codes = explode(' ', $head);
        $protocol = array_shift($codes);
        $code = array_shift($codes);
        $status = implode(' ', $codes);
        foreach ($headerLines as $line) {
            list($k, $v) = explode(":", $line);
            $k = trim($k);
            $v = trim($v);
            if ($k == 'Set-Cookie') {
                list($ck, $cv) = explode("=", $v);
                $pos = strpos($cv, ';');
                if ($pos === false) {
                    $cookies[trim($ck)] = trim($cv);
                } else {
                    $cookies[trim($ck)] = trim(substr($cv, 0, $pos));
                }
            } else {
                $headers[$k] = $v;
            }
        }
        $res = new Response();
        $res->header = $headers;
        $res->protocol = $protocol;
        $res->code = intval($code);
        $res->status = $status;
        $res->cookie = $cookies;
        $res->content = $body;
        return $res;
    }
}
