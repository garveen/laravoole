<?php
namespace Laravoole;

class Parser
{

    protected static $uploaded_file_overrided = false;

    public static function parseHeaderLine($headerLines)
    {
        if (is_string($headerLines)) {
            $headerLines = strtr($headerLines, "\r", "");
            $headerLines = explode("\n", $headerLines);
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

    public static function parseQueryString($request)
    {
        if (!isset($request->server['QUERY_STRING'])) {
            return;
        }
        $getParams = [];
        parse_str($request->server['QUERY_STRING'], $getParams);
        $request->get = empty($getParams) ? null : $getParams;
    }

    public static function parseBody($request)
    {
        if (!isset($request->header['Content-Type'])) {
            return;
        }
        if (strpos($request->header['Content-Type'], 'multipart/form-data') !== false) {
            $cd = strstr($request->header['Content-Type'], 'boundary');
            if ($cd !== false) {
                static::parseFormData($request, $cd);
                return;
            }
        }
        if (substr($request->header['Content-Type'], 0, 33) == 'application/x-www-form-urlencoded') {
            parse_str($request->rawContent(), $request->post);
        }
    }

    public static function parseCookie($request)
    {
        if (!isset($request->header['Cookie'])) {
            return;
        }
        $request->cookie = static::parseParams($request->header['Cookie']);
        foreach ($request->cookie as &$v) {
            $v = urldecode($v);
        }
    }

    public static function parseFormData($request, $orig_boundary)
    {
        $orig_boundary = str_replace('boundary=', '', $orig_boundary);

        $boundary_next = "\n--$orig_boundary";

        $boundary_next_len = strlen($boundary_next);

        $boundary = "--$orig_boundary";

        $rawContent = $request->rawContent();

        $rawContent_len = strlen($rawContent);

        $current = strpos($rawContent, $boundary) + strlen($boundary);

        do {
            $boundary_start = $current;
            if($boundary_start > $rawContent_len) {
                break;
            }

            $chr = $rawContent[$boundary_start];

            if ($chr == '-' && $rawContent[$boundary_start + 1] == '-') {
                break;
            }

            while ($chr == "\n" || $chr == "\r") {
                $boundary_start++;
                if ($boundary_start > $rawContent_len) {
                    break 2;
                }

                $chr = $rawContent[$boundary_start];
            }

            // $boundary_start pointed at the first column of meta

            $current = $boundary_start;

            do {
                $current++;
                if ($current > $rawContent_len) {
                    break 2;
                }

            } while ($rawContent[$current] != "\n" || ($rawContent[$current + 1] != "\r" && $rawContent[$current] + 1 != "\n"));

            if ($rawContent[$current - 1] == "\r") {
                $len = $current - $boundary_start - 1;
            } else {
                $len = $current - $boundary_start;
            }
            // $current pointed at \n

            $line = substr($rawContent, $boundary_start, $len);

            $head = static::parseHeaderLine($line);

            $meta = static::parseParams($head['Content-Disposition']);
            $meta = array_change_key_case($meta);

            do {
                $current++;
                if ($current > $rawContent_len) {
                    break 2;
                }

            } while ($rawContent[$current] != "\n");


            $current++;
            // $current pointed at the beginning of value
            $uploading = isset($meta['filename']);
            if (!$uploading) {

                $boundary_end = strpos($rawContent, $boundary_next, $current);

                if ($rawContent[$boundary_end - 1] == "\r") {
                    $len = $boundary_end - $current - 1;
                } else {
                    $len = $boundary_end - $current;
                }

                $value = substr($rawContent, $current, $len);
                $current = $boundary_end;
                $item = &static::getVariableRegisterTarget($arr, $meta);
                $item = $value;
                unset($item);
                // var_dump($arr);

                $request->post += $arr;

            } else {
                // upload file
                $tempdir = $request->getTempDir();
                $filename = tempnam($tempdir, 'laravoole_upload_');
                $fp = fopen($filename, 'w');
                $file_start = $current;
                $file_status = UPLOAD_ERR_EXTENSION;
                do {
                    $buf = substr($rawContent, $current, 8192);
                    if (!$buf) {
                        break;
                    }
                    $found = strpos($buf, $boundary_next);
                    if ($found !== false) {
                        if($buf[$found - 1] == "\r") {
                            $len = $found - 1;
                        } else {
                            $len = $found;
                        }
                        $buf = substr($buf, 0, $len);
                        $current += $found;
                        $file_status = UPLOAD_ERR_OK;
                        fwrite($fp, $buf);
                        break;
                    } else {
                        $current += 8192;
                        fwrite($fp, $buf);
                    }
                } while ($found === false);
                fclose($fp);


                $value = [
                    'name' => $meta['filename'],
                    'type' => $head['Content-Type'],
                    'size' => $current - $file_start,
                    'error' => $file_status,
                    'tmp_name' => $filename,
                ];
                $arr = '';
                $item = &static::getVariableRegisterTarget($arr, $meta);
                $item = $value;
                unset($item);
                $request->files += $arr;

                $item = &static::getVariableRegisterTarget($arr, $meta);
                $item = $meta['filename'];
                UploadedFile::$files[$filename] = true;
                unset($item);
                $request->post += $arr;
                if (!static::$uploaded_file_overrided) {
                    require __DIR__ . DIRECTORY_SEPARATOR . 'override' . DIRECTORY_SEPARATOR . '_uploaded_file.php';
                    static::$uploaded_file_overrided = true;
                }
            }


            $current += $boundary_next_len;
        } while (1);

    }

    public static function & getVariableRegisterTarget(&$arr, $meta)
    {
        parse_str($meta['name'], $arr);

        $arr0 = &$arr;
        $i = 0;

        while (is_array($item = &${"arr$i"}[array_keys(${"arr$i"})[0]])) {
            $i++;
            ${"arr$i"} = &$item;
            unset(${'arr' . ($i - 1)});
        }
        unset(${"arr$i"});
        return $item;
    }

}
