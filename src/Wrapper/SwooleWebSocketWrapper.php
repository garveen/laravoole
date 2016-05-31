<?php
namespace Laravoole\Wrapper;

use swoole_websocket_server;

use Laravoole\Request;
use Laravoole\Response;

class SwooleWebSocketWrapper extends SwooleHttpWrapper implements ServerInterface
{
    public function __construct($host, $port)
    {
        $this->server = new swoole_websocket_server($host, $port);
    }

    const WRAPPER = SwooleWebSocketWrapper::class;

    protected $connections = [];

    public function start()
    {
        if (!empty($this->settings)) {
            $this->server->set($this->settings);
        }

        $this->server->on('Start', [$this, 'onServerStart']);
        $this->server->on('Shutdown', [$this, 'onServerShutdown']);
        $this->server->on('WorkerStart', [$this, 'onWorkerStart']);
        $this->server->on('Open', [$this, 'onOpen']);
        $this->server->on('Message', [$this, 'onMessage']);
        $this->server->on('Close', [$this, 'onClose']);

        $this->server->start();
    }

    public function onOpen(swoole_websocket_server $server, $request)
    {
        $laravooleRequest = new Request($request->fd);
        foreach ($request as $k => $v) {
            $laravooleRequest->$k = $v;
        }
        $this->connections[$request->fd] = $laravooleRequest;
        $server->push($request->fd, getpid());

    }

    public function onMessage(swoole_websocket_server $server, $frame)
    {
        $request = $this->connections[$frame->fd];

        $data = json_decode($frame->data);

        $request->server['request_uri'] = $data->m;
        $request->get = (array) ($data->p);
        $request = $this->ucHeaders($request);

        $response = new Response($this, $request);

        $illuminateRequest = $this->dealWithRequest($request, IlluminateRequestWrapper::class);
        if (isset($request->laravooleBackups)) {
            foreach ($request->laravooleBackups as $k => $v) {
                $illuminateRequest->$k = $v;
            }
        }

        $this->onRequest($request, $response, $illuminateRequest);
        $request->laravooleBackups = $illuminateRequest->laravooleBackups;

    }

    public function endResponse($response, $content)
    {
        $this->server->push($response->request->fd, $content);

    }

    public function response($request, $out)
    {
        fwrite(STDOUT, $out);
        $this->server->push($request->fd, $out);
    }

    public function onClose($server, $fd)
    {
        unset($this->connections[$fd]);
    }

}
