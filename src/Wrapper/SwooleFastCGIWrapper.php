<?php
namespace Laravoole\Wrapper;

use Laravoole\Protocol\FastCGI;
use swoole_server;

class SwooleFastCGIWrapper extends Swoole implements ServerInterface
{
    use FastCGI;
    public function __construct($host, $port)
    {
        $this->server = new swoole_server($host, $port);
    }

    public function start($config, $settings)
    {
        // override
        $config['deal_with_public'] = false;
        parent::init($config);
        $this->settings = $settings;

        if (!empty($this->settings)) {
            $this->server->set($this->settings);
        }
        $this->server->on('Start', [$this, 'onServerStart']);
        $this->server->on('Receive', [$this, 'onReceive']);
        $this->server->on('Shutdown', [$this, 'onServerShutdown']);
        $this->server->on('WorkerStart', [$this, 'onWorkerStart']);

        $this->server->start();
    }

    public function onReceive($serv, $fd, $from_id, $data)
    {
        return $this->receive($fd, $data);
    }
}
