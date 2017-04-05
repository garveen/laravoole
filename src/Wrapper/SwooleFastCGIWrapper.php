<?php
namespace Laravoole\Wrapper;

use Garveen\FastCgi\FastCgi;
use swoole_server;

class SwooleFastCGIWrapper extends Swoole implements ServerInterface
{
    protected $max_request = 0;
    protected $request_count = 0;

    public function __construct($host, $port)
    {
        $this->server = new swoole_server($host, $port);
    }

    public function start()
    {
        if (!empty($this->handler_config)) {
            if (isset($this->handler_config['max_request'])) {
                $this->max_request = $this->handler_config['max_request'];
            }
            $this->handler_config['max_request'] = 0;
            $this->server->set($this->handler_config);
        }

        $this->callbacks = array_merge([
            'Receive' => [$this, 'onReceive'],
        ], $this->callbacks);

        return parent::start();
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
        if ($this->max_request) {
            if ($this->request_count++ > $this->max_request) {
                $this->server->stop(); // @codeCoverageIgnore
            }
        }

    }

    public function onWorkerStart($serv, $worker_id)
    {
        fwrite(STDOUT, "Swoole worker $worker_id starting\n");
        parent::onWorkerStart($serv, $worker_id);
        $this->fastcgi = new FastCgi([$this, 'requestCallback'], [$this, 'sendCallback'], [$this, 'closeCallback'], function ($level, $info) {
            fwrite(STDOUT, "$level $info");
        });
        // override
        config(['laravoole.base_config.deal_with_public' => false]);
    }
}
