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
        $this->callbacks = array_merge([
            'Request' => [$this, 'onRequest'],
        ], $this->callbacks);
        parent::start();
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
        $illuminate_response = parent::handleRequest($request);
        return $this->handleResponse($response, $illuminate_response);
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


    protected function handleResponse($response, $illuminate_response)
    {

        $accept_gzip = config('laravoole.base_config.gzip') && isset($request->header['Accept-Encoding']) && stripos($request->header['Accept-Encoding'], 'gzip') !== false;
        // status
        $response->status($illuminate_response->getStatusCode());
        // headers
        $response->header('Server', config('laravoole.base_config.server'));
        foreach ($illuminate_response->headers->allPreserveCase() as $name => $values) {
            foreach ($values as $value) {
                $response->header($name, $value);
            }
        }
        // cookies
        foreach ($illuminate_response->headers->getCookies() as $cookie) {
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
        $content = $illuminate_response->getContent();

        // check gzip
        if ($accept_gzip && isset($response->header['Content-Type'])) {
            $mime = $response->header['Content-Type'];
            if (strlen($content) > config('laravoole.base_config.gzip_min_length') && is_mime_gzip($mime)) {
                $response->gzip(config('laravoole.base_config.gzip'));
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
