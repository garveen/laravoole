<?php
namespace Laravoole\Wrapper;

use swoole_server;

class Swoole implements ServerInterface
{
    protected $swoole_server;
    public function __construct($host, $port) {
        $this->swoole_server = new swoole_server($host, $port);

    }
    public function on($event, callable $callback)
    {
        return $this->swoole_server->on($event, $callback);
    }

    public function set($settings)
    {
        return $this->swoole_server->set($settings);
    }

    public function start()
    {
        return $this->swoole_server->start();
    }

    public function send($fd, $content)
    {
        return $this->swoole_server->send($fd, $content);
    }

    public function close($fd)
    {
        return $this->swoole_server->close($fd);
    }

    public function getPid()
    {
        return $this->swoole_server->master_pid;
    }
}
