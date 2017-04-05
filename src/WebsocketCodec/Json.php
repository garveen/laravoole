<?php

namespace Laravoole\WebsocketCodec;

class Json implements CodecInterface
{
    public static function encode($statusCode, $method, $content, $echo)
    {
        return json_encode([
            's' => $statusCode,
            'm' => $method,
            'p' => $content,
            'e' => $echo,
        ]);
    }

    public static function decode($data)
    {
        $data = @json_decode($data);
        if(!isset($data->m) || !isset($data->p)) {
            return;
        }
        return [
            'method' => $data->m,
            'params' => $data->p,
            'echo' => isset($data->e) ? $data->e : null,
        ];
    }
}
