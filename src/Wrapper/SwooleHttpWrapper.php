<?php
namespace Laravoole\Wrapper;

use swoole_http_server;

class SwooleHttpWrapper extends Swoole implements ServerInterface
{

    public function __construct($host, $port)
    {
        $this->server = new swoole_http_server($host, $port);
    }


    public function start($config, $settings)
    {
        $this->init($config);
        $this->settings = $settings;

        if (!empty($this->settings)) {
            $this->server->set($this->settings);
        }
        $this->server->on('Start', [$this, 'onServerStart']);
        $this->server->on('Shutdown', [$this, 'onServerShutdown']);
        $this->server->on('WorkerStart', [$this, 'onWorkerStart']);
        $this->server->on('Request', [$this, 'onRequest']);

        $this->server->start();
    }

}
