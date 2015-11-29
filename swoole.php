<?php
namespace Laravoole;

use ErrorException;

use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Contracts\Http\Kernel;

use swoole_http_server;
use swoole_process;

class Server
{
    protected $swoole_http_server;
    protected $laravel_kernel;
    protected $pid_file;
    protected $root_dir;
    protected $deal_with_public;
    protected $public_path;

    public function __construct($host, $port, $root_dir, $pid_file, $deal_with_public, $params = [])
    {
        $this->swoole_http_server = new swoole_http_server($host, $port);
        $this->pid_file = $pid_file;
        $this->root_dir = $root_dir;
        $this->deal_with_public = $deal_with_public;

        if (!empty($params)) {
            $this->swoole_http_server->set($params);
        }

    }

    public function start()
    {
        $this->swoole_http_server->on('start', [$this, 'onServerStart']);
        $this->swoole_http_server->on('shutdown', [$this, 'onServerShutdown']);
        $this->swoole_http_server->on('WorkerStart', [$this, 'onWorkerStart']);
        $this->swoole_http_server->on('request', [$this, 'onRequest']);

        require __DIR__ . '/mime.php';

        $this->swoole_http_server->start();
    }

    public function onServerStart($serv)
    {
        // put pid
        file_put_contents(
            $this->pid_file,
            $serv->master_pid
        );
    }

    public function onServerShutdown($serv)
    {
        unlink($this->pid_file);
    }

    public function onWorkerStart($serv, $worker_id)
    {
        // bootstrap laravel here to enable reload
        require $this->root_dir . '/bootstrap/autoload.php';
        $app = require $this->root_dir . '/bootstrap/app.php';
        $this->laravel_kernel = $app->make(Kernel::class);

        $this->public_path = public_path();

    }

    public function onRequest($request, $response)
    {
        if ($this->deal_with_public) {
            if ($this->dealWithPublic($request, $response)) {
                return;
            }
        }
        try {
            $illuminate_request = $this->dealWithRequest($request);
            $illuminate_response = $this->laravel_kernel->handle($illuminate_request);
            $this->dealWithResponse($response, $illuminate_response);

        } catch (ErrorException $e) {
            if (strncmp($e->getMessage(), 'swoole_', 7) === 0) {
                fwrite(STDOUT, $e->getFile() . '(' . $e->getLine() . '): ' . $e->getMessage() . PHP_EOL);
            }
        } finally {
            $this->laravel_kernel->terminate($illuminate_request, $illuminate_response);
        }

    }

    private function dealWithRequest($request)
    {
        $get = isset($request->get) ? $request->get : array();
        $post = isset($request->post) ? $request->post : array();
        $cookie = isset($request->cookie) ? $request->cookie : array();
        $server = isset($request->server) ? $request->server : array();
        $header = isset($request->header) ? $request->header : array();
        $files = isset($request->files) ? $request->files : array();

        // merge headers into server which ar filted by swoole
        // make a new array when php 7 has different behavior on foreach
        $new_header = [];
        foreach ($header as $key => $value) {
            $new_header['http_' . $key] = $value;
        }
        $server = array_merge($server, $new_header);

        $new_server = [];
        // swoole has changed all keys to lower case
        foreach ($server as $key => $value) {
            $new_server[strtoupper($key)] = $value;
        }

        $content = $request->rawContent() ?: null;

        $illuminate_request = new IlluminateRequest($get, $post, []/* attributes */, $cookie, $files, $new_server, $content);

        return $illuminate_request;
    }

    private function dealWithResponse($response, $illuminate_response)
    {
        // status
        $response->status($illuminate_response->getStatusCode());
        // headers
        foreach ($illuminate_response->headers->allPreserveCase() as $name => $values) {
            foreach ($values as $value) {
                $response->header($name, $value);
            }
        }
        // cookies
        foreach ($illuminate_response->headers->getCookies() as $cookie) {
            $response->rawcookie(
                $cookie->getName(),
                $cookie->getValue(),
                $cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain(),
                $cookie->isSecure(),
                $cookie->isHttpOnly()
            );
        }
        // content
        $content = $illuminate_response->getContent();
        // send content & close
        $response->end($content);
    }

    private function dealWithPublic($request, $response)
    {
        $uri = $request->server['request_uri'];
        $file = realpath($this->public_path . $uri);
        if (is_file($file)) {
            if (!strncasecmp($file, $uri, strlen($this->public_path))) {
                $response->status(403);
                $response->end();
            } else {
                $response->header('Content-Type', get_mime_type($file));
                $response->sendfile($file);
            }
            return true;

        } else {
        }
        return false;

    }
}
