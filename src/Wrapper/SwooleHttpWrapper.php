<?php
namespace Laravoole\Wrapper;

use swoole_http_server;

class SwooleHttpWrapper extends Swoole implements ServerInterface
{

    public function __construct($host, $port)
    {
        $this->server = new swoole_http_server($host, $port);
    }


    public function start()
    {
        if (!empty($this->handler_config)) {
            $this->server->set($this->handler_config);
        }
        parent::start();
    }

    public function onRequest($request, $response, $illuminate_request = false)
    {
        // convert request
        $request = $this->ucHeaders($request);
        // provide response callback
        return parent::onRequest($request, $response, $illuminate_request);
    }

    protected function ucHeaders($request)
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
        return $request;
    }

}
