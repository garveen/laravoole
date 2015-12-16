<?php
namespace Laravoole;

class Parser
{
    const HTTP_EOF = "\r\n\r\n";

    protected $buffer;

    public static function parseHeaderLine($headerLines)
    {
        if (is_string($headerLines)) {
            $headerLines = explode("\r\n", $headerLines);
        }
        $header = array();
        foreach ($headerLines as $_h) {
            $_h = trim($_h);
            if (empty($_h)) {
                continue;
            }

            $_r = explode(':', $_h, 2);
            $key = $_r[0];
            $value = isset($_r[1]) ? $_r[1] : '';
            $header[trim($key)] = trim($value);
        }
        return $header;
    }

    public static function parseParams($str)
    {
        $params = array();
        $blocks = explode(";", $str);
        foreach ($blocks as $b) {
            $_r = explode("=", $b, 2);
            if (count($_r) == 2) {
                list($key, $value) = $_r;
                $params[trim($key)] = trim($value, "\r\n \t\"");
            } else {
                $params[$_r[0]] = '';
            }
        }
        return $params;
    }

    public static function parseBody($request)
    {
        if (!isset($request->header['Content-Type'])) {
            return;
        }
        $cd = strstr($request->header['Content-Type'], 'boundary');
        if ($cd !== false) {
            self::parseFormData($request, $cd);
        } else {
            if (substr($request->header['Content-Type'], 0, 33) == 'application/x-www-form-urlencoded') {
                parse_str($request->body, $request->post);
            }
        }
    }

    public static function parseCookie($request)
    {
        if (!isset($request->header['Cookie'])) {
            return;
        }
        $request->cookie = self::parseParams($request->header['Cookie']);
        foreach ($request->cookie as &$v) {
            $v = urldecode($v);
        }
    }

    public static function parseFormData($request, $cd)
    {
        $cd = '--' . str_replace('boundary=', '', $cd);
        $form = explode($cd, rtrim($request->body, "-")); //去掉末尾的--
        foreach ($form as $f) {
            if ($f === '') {
                continue;
            }

            $parts = explode("\r\n\r\n", trim($f));
            $head = self::parseHeaderLine($parts[0]);
            if (!isset($head['Content-Disposition'])) {
                continue;
            }

            $meta = self::parseParams($head['Content-Disposition']);
            //filename字段表示它是一个文件
            if (!isset($meta['filename'])) {
                if (count($parts) < 2) {
                    $parts[1] = "";
                }

                //支持checkbox
                if (substr($meta['name'], -2) === '[]') {
                    $request->post[substr($meta['name'], 0, -2)][] = trim($parts[1]);
                } else {
                    $request->post[$meta['name']] = trim($parts[1], "\r\n");
                }

            } else {
                $file = trim($parts[1]);
                $tmp_file = tempnam('/tmp', 'sw');
                file_put_contents($tmp_file, $file);
                if (!isset($meta['name'])) {
                    $meta['name'] = 'file';
                }

                $request->file[$meta['name']] = array('name' => $meta['filename'],
                    'type' => $head['Content-Type'],
                    'size' => strlen($file),
                    'error' => UPLOAD_ERR_OK,
                    'tmp_name' => $tmp_file);
            }
        }
    }

}
