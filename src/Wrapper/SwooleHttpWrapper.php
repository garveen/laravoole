<?php
namespace Laravoole\Wrapper;

use swoole_http_server;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SwooleHttpWrapper extends Swoole implements ServerInterface
{
    protected $accept_gzip = false;

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
        $request = $this->ucHeaders($request);
        if (config('laravoole.base_config.deal_with_public')) {
            if ($status = $this->handleStaticFile($request, $response)) {
                return $status;
            }
        }
        // provide response callback
        $illuminateResponse = parent::handleRequest($request);
        return $this->handleResponse($request, $response, $illuminateResponse);
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


    protected function handleResponse($request, $response, $illuminateResponse)
    {

        $accept_gzip = $this->accept_gzip && isset($request->header['Accept-Encoding']) && stripos($request->header['Accept-Encoding'], 'gzip') !== false;

        // status
        $response->status($illuminateResponse->getStatusCode());
        // headers
        $response->header('Server', config('laravoole.base_config.server'));
        foreach ($illuminateResponse->headers->allPreserveCase() as $name => $values) {
            foreach ($values as $value) {
                $response->header($name, $value);
            }
        }
        // cookies
        foreach ($illuminateResponse->headers->getCookies() as $cookie) {
            $response->rawcookie(
                $cookie->getName(),
                urlencode($cookie->getValue()),
                $cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain(),
                $cookie->isSecure(),
                $cookie->isHttpOnly()
            );
        }
        // content
        if ($illuminateResponse instanceof BinaryFileResponse) {
            $content = function() use ($illuminateResponse) {
                return $illuminateResponse->getFile()->getPathname();
            };
        } else {
            $content = $illuminateResponse->getContent();
            // check gzip
            if ($accept_gzip && isset($response->header['Content-Type'])) {
                $mime = $response->header['Content-Type'];
                if (strlen($content) > config('laravoole.base_config.gzip_min_length') && is_mime_gzip($mime)) {
                    $response->gzip(config('laravoole.base_config.gzip'));
                }
            }
        }
        $this->endResponse($response, $content);
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

}
