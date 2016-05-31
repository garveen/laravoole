<?php
namespace Laravoole;

use Exception;
use ErrorException;

use Illuminate\Http\Request as IlluminateRequest;

use Illuminate\Support\Facades\Facade;

abstract class Base
{
    protected $config;

    protected $settings;

    protected $kernel;

    protected $tmp_autoloader;

    protected $app;

    protected $server;

    public function start()
    {
        throw new Exception(__CLASS__ . "::start MUST be implemented", 1);
    }

    final public function init($config, $settings)
    {
        $this->config = $config;
        $this->settings = $settings;
    }

    public function onRequest($request, $response, $illuminate_request = false)
    {
        // for file system
        clearstatcache();
        if ($this->config['deal_with_public']) {
            if ($this->dealWithPublic($request, $response)) {
                return;
            }
        }

        try {
            $kernel = $this->kernel;

            if(!$illuminate_request) {
                $illuminate_request = $this->dealWithRequest($request);
            }

            $illuminate_response = $kernel->handle($illuminate_request);
            // Is gzip enabled and the client accept it?
            $accept_gzip = $this->config['gzip'] && isset($request->header['Accept-Encoding']) && stripos($request->header['Accept-Encoding'], 'gzip') !== false;

            $this->dealWithResponse($response, $illuminate_response, $accept_gzip);

        } catch (\Exception $e) {
            echo '[ERR] ' . $e->getFile() . '(' . $e->getLine() . '): ' . $e->getMessage() . PHP_EOL;
            echo $e->getTraceAsString() . PHP_EOL;
        } catch (\Throwable $e) {
            echo '[ERR] ' . $e->getFile() . '(' . $e->getLine() . '): ' . $e->getMessage() . PHP_EOL;
            echo $e->getTraceAsString() . PHP_EOL;
        } finally {
            if (isset($illuminate_response)) {
                $kernel->terminate($illuminate_request, $illuminate_response);
            }
            if ($illuminate_request->hasSession()) {
                $illuminate_request->getSession()->clear();
            }

            if ($this->app->isProviderLoaded(Illuminate\Auth\AuthServiceProvider::class)) {
                $this->app->register(\Illuminate\Auth\AuthServiceProvider::class, [], true);
                Facade::clearResolvedInstance('auth');
            }

            return $response;
        }

    }

    protected function dealWithRequest($request, $classname = IlluminateRequest::class)
    {

        $get = isset($request->get) ? $request->get : array();
        $post = isset($request->post) ? $request->post : array();
        $cookie = isset($request->cookie) ? $request->cookie : array();
        $server = isset($request->server) ? $request->server : array();
        $header = isset($request->header) ? $request->header : array();
        $files = isset($request->files) ? $request->files : array();
        // $attr = isset($request->files) ? $request->files : array();

        $content = $request->rawContent() ?: null;

        $illuminate_request = new $classname($get, $post, []/* attributes */, $cookie, $files, $server, $content);

        return $illuminate_request;
    }

    private function dealWithResponse($response, $illuminate_response, $accept_gzip)
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
            if (strlen($content) > $this->config['gzip_min_length'] && is_mime_gzip($mime)) {
                $response->gzip($this->config['gzip']);
            }
        }
        $this->endResponse($response, $content);
    }

    public function endResponse($response, $content)
    {
        // send content & close
        $response->end($content);
    }

    protected function dealWithPublic($request, $response)
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

    protected function getApp()
    {

        $app = new App($this->config['root_dir']);

        $app->singleton(
            \Illuminate\Contracts\Http\Kernel::class,
            \App\Http\Kernel::class
        );

        $app->singleton(
            \Illuminate\Contracts\Console\Kernel::class,
            \App\Console\Kernel::class
        );

        $app->singleton(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            \App\Exceptions\Handler::class
        );

        return $app;
    }

}
