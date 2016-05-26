<?php
namespace Laravoole\Wrapper;

use swoole_websocket_server;

class SwooleWebSocketWrapper extends SwooleHttpWrapper implements ServerInterface
{
    protected $server;

    public function __construct($host, $port) {
        $this->server = new swoole_websocket_server($host, $port);
    }
}
