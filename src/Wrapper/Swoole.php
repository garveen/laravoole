<?php
namespace Laravoole\Wrapper;

use swoole_server;

class Swoole extends SwooleHttp implements ServerInterface
{
    protected $server;

    public function __construct($host, $port) {
        $this->server = new swoole_server($host, $port);
    }
}
