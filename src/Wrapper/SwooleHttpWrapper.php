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

    public function onRequest($request, $response)
    {
        // merge headers into server which ar filted by swoole
        // make a new array when php 7 has different behavior on foreach
        $new_header = [];
        $uc_header = [];
        foreach ($request->header as $key => $value) {
            $new_header['http_' . $key] = $value;
            $uc_header[ucwords($key, '-')] = $value;
        }
        $server = array_merge($request->server, $new_header);

        // swoole has changed all keys to lower case
        $server = array_change_key_case($server, CASE_UPPER);
        $request->server = $server;
        $request->header = $uc_header;
        return parent::onRequest($request, $response);
    }

}
