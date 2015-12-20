<?php
namespace Laravoole\Wrapper;

use swoole_http_server;

class SwooleHttp implements ServerInterface
{
    protected $swoole_http_server;

    public function __construct($host, $port) {
        $this->swoole_http_server = new swoole_http_server($host, $port);
    }

    public function on($event, callable $callback)
    {
        return $this->swoole_http_server->on($event, $callback);
    }

    public function set($settings)
    {
        return $this->swoole_http_server->set($settings);
    }

    public function start()
    {
        return $this->swoole_http_server->start();
    }

    public function send($fd, $content)
    {
        return $this->swoole_http_server->send($fd, $content);
    }

    public function close($fd)
    {
        return $this->swoole_http_server->close($fd);
    }

    public function getPid()
    {
        return $this->swoole_http_server->master_pid;
    }
}
