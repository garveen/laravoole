<?php
namespace Laravoole\Wrapper;

use swoole_websocket_server;
use swoole_http_request;
use swoole_http_response;

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
    protected $protocolCodecs = [
        'json' => SwooleWebSocketCodecJson::class,
    ];

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
        $this->server->on('HandShake', [$this, 'onHandShake']);
        // $this->server->on('Open', [$this, 'onOpen']);
        $this->server->on('Message', [$this, 'onMessage']);
        $this->server->on('Close', [$this, 'onClose']);

        $this->server->start();
    }

    public function onHandShake(swoole_http_request $request, swoole_http_response $response)
    {
        $protocol = 'json';
        if(isset($request->header['sec-websocket-protocol'])) {
            $protocols = $request->header['sec-websocket-protocol'];
            $protocols = array_intersect(preg_split('~,\s*~', $protocols), array_keys($this->protocolCodecs));
            if(!empty($protocols)) {
                $protocol = $protocols[0];
                $response->header('Sec-WebSocket-Protocol', $protocol);
            }
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
        $this->connections[$request->fd] = ['request' => $laravooleRequest, 'protocol' => $this->protocolCodecs[$protocol]];
        $this->unfinished[$request->fd] = '';
        return true;

    }

    public function onMessage(swoole_websocket_server $server, $frame)
    {
        if (!isset($this->unfinished[$frame->fd])) {
            return false;
        }
        $this->unfinished[$frame->fd] .= $frame->data;
        if (!$frame->finish) {
            return;
        }
        $data = $this->connections[$frame->fd]['protocol']::decode($this->unfinished[$frame->fd]);

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
        $data = $this->connections[$response->request->fd]['protocol']::encode(
            $response->http_status,
            $response->request->method,
            $content,
            $response->request->echo
        );
        $this->server->push($response->request->fd, $data);

    }

    public function onClose($server, $fd)
    {
        unset($this->unfinished[$fd]);
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
