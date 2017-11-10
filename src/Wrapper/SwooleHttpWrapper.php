<?php
namespace Laravoole\Wrapper;

use swoole_http_server;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SwooleHttpWrapper extends Swoole implements ServerInterface
{
    use HttpTrait;

    public function __construct($host, $port)
    {
        $this->server = new swoole_http_server($host, $port);
    }

    public function start()
    {
        if (!empty($this->handler_config)) {
            $this->server->set($this->handler_config);
        }
        $this->callbacks = array_merge([
            'Request' => [$this, 'onRequest'],
        ], $this->callbacks);
        parent::start();
    }

    public function onWorkerStart($serv, $worker_id)
    {
        parent::onWorkerStart($serv, $worker_id);
        $this->accept_gzip = config('laravoole.base_config.gzip');
    }

    public function onRequest($request, $response)
    {
        // convert request
        $this->ucHeaders($request);
        if (config('laravoole.base_config.deal_with_public')) {
            if ($status = $this->handleStaticFile($request, $response)) {
                return $status;
            }
        }
        // provide response callback
        $illuminateResponse = parent::handleRequest($request);
        return $this->handleResponse($response, $illuminateResponse, isset($request->header['Accept-Encoding']) ? $request->header['Accept-Encoding'] : '');
    }

    protected function ucHeaders($request)
    {
        // merge headers into server which ar filted by swoole
        // make a new array when php 7 has different behavior on foreach
        $new_header = [];
        $uc_header = [];
        foreach ($request->header as $key => $value) {
            $new_header['http_' . strtr($key, '-', '_')] = $value;
            $uc_header[ucwords($key, '-')] = $value;
        }
        $server = array_merge($request->server, $new_header);

        // swoole has changed all keys to lower case
        $server = array_change_key_case($server, CASE_UPPER);
        $request->server = $server;
        $request->header = $uc_header;
        return $request;
    }

    protected function handleStaticFile($request, $response)
    {
        static $public_path;
        if (!$public_path) {
            $app = $this->app;
            $public_path = $app->make('path.public');

        }

        $uri = $request->server['REQUEST_URI'];
        $file = realpath($public_path . $uri);
        if (is_file($file)) {
            if (!strncasecmp($file, $uri, strlen($public_path))) {
                $response->status(403);
                $response->end();
            } else {
                $response->header('Content-Type', get_mime_type($file));
                if (!filesize($file)) {
                    $response->end();
                } else {
                    $response->sendfile($file);
                }
            }
            return true;
        }
        return false;

    }

    public function endResponse($response, $content)
    {
        if (!is_string($content)) {
            $response->sendfile(realpath($content()));
        } else {
            // send content & close
            $response->end($content);
        }
    }

}
