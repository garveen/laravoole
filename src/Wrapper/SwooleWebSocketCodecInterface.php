<?php

namespace Laravoole\Wrapper;

interface SwooleWebSocketCodecInterface
{
    public static function encode($statusCode, $method, $content, $echo);

    public static function decode($data);
}
