<?php
namespace Laravoole\Wrapper;

use Laravoole\Workerman\Worker;
use Garveen\FastCgi\FastCgi;

class WorkermanFastCGIWrapper extends Workerman implements ServerInterface
{

    public function __construct($host, $port)
    {
        require __DIR__ . '/../../../../workerman/workerman/Autoloader.php';
        $this->server = new Worker("tcp://{$host}:{$port}");
    }

    public function onWorkerStart($worker)
    {
        $worker->log("Workerman worker {$worker->id} starting\n");
        parent::onWorkerStart($worker);
        $this->fastcgi = new FastCgi([$this, 'requestCallback'], [$this, 'sendCallback'], [$this, 'closeCallback'], function($level, $info) use ($worker) {
            $worker->log("$level $info");
        });
    }

    public function onReceive($connection, $data)
    {
        $this->connections[$connection->id]['connection'] = $connection;
        return $this->fastcgi->receive($connection->id, $data);
    }

    public function requestCallback($psrRequest)
    {
        return $this->onPsrRequest($psrRequest);
    }

    public function sendCallback($fd, $payload)
    {
        $this->send($fd, $payload);
    }

    public function closeCallback($fd)
    {
        $this->close($fd);
    }

}
