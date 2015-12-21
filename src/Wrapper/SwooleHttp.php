<?php
namespace Laravoole\Wrapper;

use swoole_http_server;

class SwooleHttp implements ServerInterface
{
    protected $server;

    public function __construct($host, $port) {
        $this->server = new swoole_http_server($host, $port);
    }

    public function on($event, callable $callback)
    {
        return $this->server->on($event, $callback);
    }

    public function set($settings)
    {
        return $this->server->set($settings);
    }

    public function start()
    {
        return $this->server->start();
    }

    public function send($fd, $content)
    {
        return $this->server->send($fd, $content);
    }

    public function close($fd)
    {
        return $this->server->close($fd);
    }

    public function getPid()
    {
        return $this->server->master_pid;
    }
}
