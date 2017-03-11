<?php
namespace Laravoole\Wrapper;

use swoole_websocket_server;
use swoole_http_request;
use swoole_http_response;
use swoole_process;

use Laravoole\Request;
use Laravoole\Response;
use Laravoole\WebsocketCodec\Json;
use Laravoole\WebsocketCodec\JsonRpc;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SwooleWebSocketWrapper extends SwooleHttpWrapper implements ServerInterface
{
    protected $defaultProtocol;

    protected $pushProcess;

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

    public static function getParams()
    {
        $params = parent::getParams();
        unset($params[array_search('task_worker_num', $params)]);
        $params['task_worker_num'] = 1;
        return $params;
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
        $this->defaultProtocol = $this->wrapper_config['websocket_default_protocol'];

        static::registerCodec($this->wrapper_config['websocket_protocols']);

        $this->callbacks = array_merge([
            'HandShake' => [$this, 'onHandShake'],
            'Message' => [$this, 'onMessage'],
            'Close' => [$this, 'onClose'],
            'Task' => [static::class, 'onTask'],
            'Finish' => [static::class, 'onFinish'],
        ], $this->callbacks);
        parent::start();

    }

    public static function onTask($server, $task_id, $from_id, $data)
    {
        foreach ($data['fds'] as $fd) {
            try {
                $server->push($fd, $data['params']);
            } catch (\ErrorException $e) {}
        }
    }

    public static function onFinish($server, $task_id, $data)
    {

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
        $protocol = $this->connections[$frame->fd]['protocol'];
        $data = $protocol::decode($this->unfinished[$frame->fd]);
        if (is_null($data)) {
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
        $illuminateRequest = $this->convertRequest($request);

        if (isset($request->laravooleInfo)) {
            $illuminateRequest->setLaravooleInfo($request->laravooleInfo);
        } else {
            $illuminateRequest->setLaravooleInfo((object) [
                'fd' => $fd,
                'server' => $server,
                'codec' => $this->connections[$response->request->fd]['protocol'],
            ]);
        }
        $illuminateResponse = parent::handleRequest($illuminateRequest);
        $this->handleResponse($request, $response, $illuminateResponse);

        $request->laravooleInfo = $illuminateRequest->getLaravooleInfo();
    }

    public function endResponse($response, $content)
    {
        if (isset($response->request)) {
            if (!is_string($content)) {
                $content = file_get_contents($content());
            }
            // This is a websocket request
            $protocol = $this->connections[$response->request->fd]['protocol'];
            $data = $protocol::encode(
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
        if (!isset($this->connections[$fd])) {
            return;
        }
        $request = $this->connections[$fd]['request'];
        $this->events->fire('laravoole.swoole.websocket.closing', [$request, $fd]);

        unset($this->unfinished[$fd]);
        unset($this->connections[$fd]);

    }
}
