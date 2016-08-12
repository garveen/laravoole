<?php

namespace Laravoole\Wrapper;

class SwooleWebSocketCodecJson implements SwooleWebSocketCodecInterface
{
    public static function encode($statusCode, $method, $content, $echo, $is_upload = false)
    {
        return json_encode([
            's' => $statusCode,
            'm' => $method,
            'p' => $content,
            'e' => $echo,
            'u' => $is_upload,
        ]);
    }

    public static function decode($data)
    {
        $data = json_decode($data);
        return [
            'method' => $data->m,
            'params' => $data->p,
            'echo' => $data->e,
            'is_upload' => $data->u,
        ];
    }
}
