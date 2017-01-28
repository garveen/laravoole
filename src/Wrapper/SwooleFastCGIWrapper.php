<?php
namespace Laravoole\Wrapper;

use Garveen\FastCgi\FastCgi;
use swoole_server;

class SwooleFastCGIWrapper extends Swoole implements ServerInterface
{
    public function __construct($host, $port)
    {
        $this->server = new swoole_server($host, $port);
    }

    public function start()
    {
        if (!empty($this->handler_config)) {
            $this->server->set($this->handler_config);
        }
        $this->server->on('Start', [$this, 'onServerStart']);
        $this->server->on('Receive', [$this, 'onReceive']);
        $this->server->on('Shutdown', [$this, 'onServerShutdown']);
        $this->server->on('WorkerStart', [$this, 'onWorkerStart']);

        $this->server->start();
    }

    public function onReceive($serv, $fd, $from_id, $data)
    {

        return $this->fastcgi->receive($fd, $data);
    }

    public function requestCallback($psrRequest)
    {
        return $this->onPsrRequest($psrRequest);
    }

    public function sendCallback($fd, $payload)
    {
        $this->server->send($fd, $payload);
    }

    public function closeCallback($fd)
    {
        $this->server->close($fd);
    }

    public function onWorkerStart($serv, $worker_id)
    {
        fwrite(STDOUT, "Swoole worker $worker_id starting\n");
        parent::onWorkerStart($serv, $worker_id);
        $this->fastcgi = new FastCgi([$this, 'requestCallback'], [$this, 'sendCallback'], [$this, 'closeCallback'], function($level, $info) {
            fwrite(STDOUT, "$level $info");
        });
        // override
        config(['laravoole.base_config.deal_with_public' => false]);
    }
}
