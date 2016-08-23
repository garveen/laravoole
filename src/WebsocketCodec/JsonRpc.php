<?php

namespace Laravoole\WebsocketCodec;

class JsonRpc implements CodecInterface
{
    public static function encode($statusCode, $method, $content, $echo)
    {
        if($statusCode >= 400) {
            $error = $content;
            $content = null;
        } else {
            $error = null;
        }
        return json_encode([
            'status' => $statusCode,
            'method' => $method,
            'result' => $content,
            'error' => $error,
            'id' => $echo,
        ]);
    }

    public static function decode($data)
    {
        $data = @json_decode($data);
        if(!isset($data->method) || !isset($data->params)) {
            return;
        }
        return [
            'method' => $data->method,
            'params' => $data->params,
            'echo' => isset($data->id) ? $data->id : null,
        ];
    }
}
