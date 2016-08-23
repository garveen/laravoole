<?php
namespace Laravoole\Wrapper;

use swoole_websocket_server;
use swoole_http_request;
use swoole_http_response;

use Laravoole\Request;
use Laravoole\Response;
use Laravoole\WebsocketCodec\Json;
use Laravoole\WebsocketCodec\JsonRpc;

class SwooleWebSocketWrapper extends SwooleHttpWrapper implements ServerInterface
{
    protected $defaultProtocol;

    protected $connections = [];

    protected $unfinished = [];

    protected static $protocolCodecs = [
        'json' => Json::class,
        'jsonrpc' => JsonRpc::class,
    ];

    public function __construct($host, $port)
    {
        $this->server = new swoole_websocket_server($host, $port);
    }

    public static function getExtParams()
    {
        return [
            'websocket_default_protocol' => 'jsonrpc',
            'websocket_protocols',
            'websocket_protocol_handlers',
        ];
    }

    public static function registerCodec($protocol, $class = null)
    {
        if (is_string($protocol)) {
            if (!class_exists($class)) {
                throw new Exception("class $class not found", 1);
            }
            $protocol = [$protocol => $class];
        }
        static::$protocolCodecs = array_merge(static::$protocolCodecs, $protocol);
    }

    public function start()
    {
        if (!empty($this->handler_config)) {
            $this->server->set($this->handler_config);
        }

        $this->defaultProtocol = $this->wrapper_config['websocket_default_protocol'];

        static::registerCodec($this->wrapper_config['websocket_protocols']);

        $this->server->on('Start', [$this, 'onServerStart']);
        $this->server->on('Shutdown', [$this, 'onServerShutdown']);
        $this->server->on('WorkerStart', [$this, 'onWorkerStart']);
        $this->server->on('HandShake', [$this, 'onHandShake']);
        $this->server->on('Message', [$this, 'onMessage']);
        $this->server->on('Request', [$this, 'onRequest']);
        $this->server->on('Close', [$this, 'onClose']);

        $this->server->start();
    }

    public function onHandShake(swoole_http_request $request, swoole_http_response $response)
    {
        $protocol = false;
        if (isset($request->header['sec-websocket-protocol'])) {
            $protocols = preg_split('~,\s*~', $request->header['sec-websocket-protocol']);
            foreach ($protocols as $protocol) {
                if (isset(static::$protocolCodecs[$protocol])) {
                    break;
                }
            }
            if ($protocol) {
                $response->header('Sec-WebSocket-Protocol', $protocol);
            }
        }

        if (!$protocol) {
            $protocol = $this->defaultProtocol;
        }

        $secKey = $request->header['sec-websocket-key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

        foreach ([
            "Upgrade" => "websocket",
            "Connection" => "Upgrade",
            "Sec-WebSocket-Version" => "13",
            "Sec-WebSocket-Accept" => $secAccept,
        ] as $k => $v) {
            $response->header($k, $v);
        }
        $response->status(101);

        $laravooleRequest = new Request($request->fd);
        foreach ($request as $k => $v) {
            $laravooleRequest->$k = $v;
        }
        $this->connections[$request->fd] = ['request' => $laravooleRequest, 'protocol' => static::$protocolCodecs[$protocol]];
        $this->unfinished[$request->fd] = '';
        return true;

    }

    public function onMessage(swoole_websocket_server $server, $frame)
    {
        if (!isset($this->unfinished[$frame->fd])) {
            return false;
        }
        if (isset($this->connections[$frame->fd]['request']->laravooleInfo->nextMessageRoute)) {
            $request = $this->connections[$frame->fd]['request'];
            $route = $request->laravooleInfo->nextMessageRoute;
            $data['method'] = $route['method'];
            $data['params'] = $route['params'];
            $data['params']['_laravoole_raw'] = $frame->data;
            $data['params']['_laravoole_previous'] = $route['previous'];

            $data['echo'] = $request->echo;
            if ($frame->finish) {
                unset($request->laravooleInfo->nextMessageRoute);
            }
            return $this->dispatch($server, $frame->fd, $data);

        } else {
            $this->unfinished[$frame->fd] .= $frame->data;
        }

        if (!$frame->finish) {
            return;
        }
        $data = $this->connections[$frame->fd]['protocol']::decode($this->unfinished[$frame->fd]);
        if(is_null($data)) {
            return;
        }

        $this->unfinished[$frame->fd] = '';

        return $this->dispatch($server, $frame->fd, $data);

    }

    protected function dispatch($server, $fd, $data)
    {
        $request = $this->connections[$fd]['request'];

        $request->method = $request->server['request_uri'] = $data['method'];
        $request->get = (array) ($data['params']);
        $request->echo = isset($data['echo']) ? $data['echo'] : null;
        $request = $this->ucHeaders($request);

        $response = new Response($this, $request);

        $illuminateRequest = $this->dealWithRequest($request);

        if (isset($request->laravooleInfo)) {
            $illuminateRequest->setLaravooleInfo($request->laravooleInfo);
        } else {
            $illuminateRequest->setLaravooleInfo((object) [
                'fd' => $fd,
                'server' => $server,
            ]);
        }

        $this->onRequest($request, $response, $illuminateRequest);

        $request->laravooleInfo = $illuminateRequest->getLaravooleInfo();
    }

    public function endResponse($response, $content)
    {
        if (isset($response->request)) {
            // This is a websocket request
            $data = $this->connections[$response->request->fd]['protocol']::encode(
                $response->http_status,
                $response->request->method,
                $content,
                $response->request->echo
            );
            $this->server->push($response->request->fd, $data);
        } else {
            // This is a http request
            parent::endResponse($response, $content);
        }
    }

    public function onClose($server, $fd)
    {
        unset($this->unfinished[$fd]);
        unset($this->connections[$fd]);
        if (isset($this->wrapper_config['LARAVOOLE_WEBSOCKET_CLOSE_CALLBACK'])) {
            $data = new \stdClass;
            $data->m = $this->wrapper_config['LARAVOOLE_WEBSOCKET_CLOSE_CALLBACK'];
            $data->p = [];
            $data->e = null;
            $this->dispatch($server, $fd, $data);
        }
    }
}
