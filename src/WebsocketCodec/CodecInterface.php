<?php

namespace Laravoole\WebsocketCodec;

interface CodecInterface
{
    public static function encode($statusCode, $method, $content, $echo);

    public static function decode($data);
}
