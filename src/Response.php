<?php
namespace Laravoole;

class Response
{
    public $http_protocol = 'HTTP/1.1';
    public $http_status = 200;

    public $head;
    public $cookie;
    public $body;

    static $HTTP_HEADERS = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',            // RFC2518
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',          // RFC4918
        208 => 'Already Reported',      // RFC5842
        226 => 'IM Used',               // RFC3229
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Reserved',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',    // RFC7238
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',                                               // RFC2324
        422 => 'Unprocessable Entity',                                        // RFC4918
        423 => 'Locked',                                                      // RFC4918
        424 => 'Failed Dependency',                                           // RFC4918
        425 => 'Reserved for WebDAV advanced collections expired proposal',   // RFC2817
        426 => 'Upgrade Required',                                            // RFC2817
        428 => 'Precondition Required',                                       // RFC6585
        429 => 'Too Many Requests',                                           // RFC6585
        431 => 'Request Header Fields Too Large',                             // RFC6585
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates (Experimental)',                      // RFC2295
        507 => 'Insufficient Storage',                                        // RFC4918
        508 => 'Loop Detected',                                               // RFC5842
        510 => 'Not Extended',                                                // RFC2774
        511 => 'Network Authentication Required',                             // RFC6585
    );

    protected $protocol;
    public $request;

    public function __construct($protocol, $request)
    {
        $this->protocol = $protocol;
        $this->request = $request;
    }

    /**
     * 设置Http状态
     * @param $code
     */
    public function status($code)
    {
        // $this->head[0] = $this->http_protocol.' '.self::$HTTP_HEADERS[$code];
        $this->http_status = $code;
    }

    /**
     * 设置Http头信息
     * @param $key
     * @param $value
     */
    public function header($key, $value)
    {
        $this->head[$key] = $value;
    }

    /**
     * 设置COOKIE
     * @param $name
     * @param null $value
     * @param null $expire
     * @param string $path
     * @param null $domain
     * @param null $secure
     * @param null $httponly
     */
    public function rawcookie($name, $value = null, $expire = null, $path = '/', $domain = null, $secure = null, $httponly = null)
    {
        if ($value == null) {
            $value = 'deleted';
        }
        $cookie = "$name=$value";
        if ($expire) {
            $cookie .= "; expires=" . date("D, d-M-Y H:i:s T", $expire);
        }
        if ($path) {
            $cookie .= "; path=$path";
        }
        if ($secure) {
            $cookie .= "; secure";
        }
        if ($domain) {
            $cookie .= "; domain=$domain";
        }
        if ($httponly) {
            $cookie .= '; httponly';
        }
        $this->cookie[] = $cookie;
    }

    /**
     * 添加http header
     * @param $header
     */
    public function addHeaders(array $header)
    {
        $this->head = array_merge($this->head, $header);
    }

    public function getHeader($fastcgi = false)
    {
        $out = '';
        if ($fastcgi) {
            $out .= 'Status: ' . $this->http_status . ' ' . static::$HTTP_HEADERS[$this->http_status] . "\r\n";
        } else {
            //Protocol
            if (isset($this->head[0])) {
                $out .= $this->head[0] . "\r\n";
                unset($this->head[0]);
            } else {
                $out = "HTTP/1.1 200 OK\r\n";
            }
        }
        //fill header
        if (!isset($this->head['Server'])) {
            $this->head['Server'] = 'php';
        }
        if (!isset($this->head['Content-Type'])) {
            $this->head['Content-Type'] = 'text/html'; //charset=
        }
        if (!isset($this->head['Content-Length'])) {
            $this->head['Content-Length'] = strlen($this->body);
        }
        //Headers
        foreach ($this->head as $k => $v) {
            $out .= $k . ': ' . $v . "\r\n";
        }
        //Cookies
        if (!empty($this->cookie) and is_array($this->cookie)) {
            foreach ($this->cookie as $v) {
                $out .= "Set-Cookie: $v\r\n";
            }
        }
        //End
        $out .= "\r\n";
        return $out;
    }

    public function noCache()
    {
        $this->head['Cache-Control'] = 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0';
        $this->head['Pragma'] = 'no-cache';
    }
    public function end($content)
    {
        $this->header('Content-Length', strlen($content));
        $out = $this->getHeader(true);
        $out .= $content;
        $protocol = $this->protocol;
        $protocol::response($this->request, $out);
    }
    public function sendfile($file)
    {
        $this->head['x-sendfile'] = $file;
    }
}

class ResponseException extends \Exception
{

}
