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

    protected $connections = [];
    protected $unfinished = [];

    public static function getDefaults()
    {
        return array_merge(parent::getDefaults(), ['LARAVOOLE_WEBSOCKET_CLOSE_CALLBACK' => null]);
    }

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
        $this->unfinished[$request->fd] = '';

    }

    public function onMessage(swoole_websocket_server $server, $frame)
    {
        $this->unfinished[$frame->fd] .= $frame->data;
        if(!$frame->finish) {
            return;
        }
        $data = json_decode($this->unfinished[$frame->fd]);

        $this->unfinished[$frame->fd] = '';

        return $this->dispatch($server, $frame->fd, $data);

    }

    protected function dispatch($server, $fd, $data)
    {
        $request = $this->connections[$fd];

        $request->method = $request->server['request_uri'] = $data->m;
        $request->get = (array) ($data->p);
        $request->echo = isset($data->e) ? $data->e : null;
        $request = $this->ucHeaders($request);

        $response = new Response($this, $request);

        $illuminateRequest = $this->dealWithRequest($request, IlluminateRequestWrapper::class);

        $illuminateRequest->macro('getSwooleFd', function() use ($fd) {
            return $fd;
        });

        $illuminateRequest->macro('getSwooleServer', function() use ($server) {
            return $server;
        });

        if (isset($request->userResolver) && $request->userResolver) {
            $illuminateRequest->macro('laravooleUserResolver', $request->userResolver);
        }
        $this->onRequest($request, $response, $illuminateRequest);
        if ($illuminateRequest->laravooleIssetUserResolver) {
            $request->userResolver = $illuminateRequest->laravooleIssetUserResolver;
        }
    }

    public function endResponse($response, $content)
    {
        $this->server->push($response->request->fd, json_encode([
            's' => $response->http_status,
            'm' => $response->request->method,
            'p' => $content,
            'e' => $response->request->echo,
        ]));

    }

    public function onClose($server, $fd)
    {
        unset($this->unfinished[$frame->fd]);
        unset($this->connections[$fd]);
        if (isset($this->settings['LARAVOOLE_WEBSOCKET_CLOSE_CALLBACK'])) {
            $data = new \stdClass;
            $data->m = $this->settings['LARAVOOLE_WEBSOCKET_CLOSE_CALLBACK'];
            $data->p = [];
            $data->e = null;
            $this->dispatch($server, $fd, $data);
        }
    }

}
