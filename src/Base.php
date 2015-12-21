<?php
namespace Laravoole;

use Exception;
use ErrorException;

use Illuminate\Http\Request as IlluminateRequest;

use Illuminate\Support\Facades\Facade;


abstract class Base
{

    protected static $kernel;
    protected static $pid_file;
    protected static $root_dir;
    protected static $deal_with_public;
    protected static $gzip;
    protected static $gzip_min_length;
    protected static $host;
    protected static $port;

    protected static $tmp_autoloader;

    protected static $settings;

    protected static $app;

    protected static $server;

    public static function start($config, $settings)
    {
        throw new Exception(__CLASS__ . "::start MUST be implemented", 1);
    }

    public static function onWorkerStart($serv, $worker_id)
    {
        // unregister temporary autoloader
        foreach (spl_autoload_functions() as $function) {
            spl_autoload_unregister($function);
        }

        require static::$root_dir . '/bootstrap/autoload.php';
        static::$app = static::getApp();

        static::$kernel = static::$app->make(\Illuminate\Contracts\Http\Kernel::class);

    }

    public static function onRequest($request, $response)
    {
        // for file system
        clearstatcache();
        if (static::$deal_with_public) {
            if (static::dealWithPublic($request, $response)) {
                return;
            }
        }

        try {
            $kernel = static::$kernel;

            $illuminate_request = static::dealWithRequest($request);

            $illuminate_response = $kernel->handle($illuminate_request);
            // Is gzip enabled and the client accept it?
            $accept_gzip = static::$gzip && isset($request->header['accept-encoding']) && stripos($request->header['accept-encoding'], 'gzip') !== false;

            static::dealWithResponse($response, $illuminate_response, $accept_gzip);

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

            if (static::$app->isProviderLoaded(Illuminate\Auth\AuthServiceProvider::class)) {
                static::$app->register(\Illuminate\Auth\AuthServiceProvider::class, [], true);
                Facade::clearResolvedInstance('auth');
            }

            return $response;
        }

    }

    private static function dealWithRequest($request)
    {

        $get = isset($request->get) ? $request->get : array();
        $post = isset($request->post) ? $request->post : array();
        $cookie = isset($request->cookie) ? $request->cookie : array();
        $server = isset($request->server) ? $request->server : array();
        $header = isset($request->header) ? $request->header : array();
        $files = isset($request->files) ? $request->files : array();
        // $attr = isset($request->files) ? $request->files : array();

        // merge headers into server which ar filted by swoole
        // make a new array when php 7 has different behavior on foreach
        $new_header = [];
        foreach ($header as $key => $value) {
            $new_header['http_' . $key] = $value;
        }
        $server = array_merge($server, $new_header);

        // swoole has changed all keys to lower case
        $server = array_change_key_case($server, CASE_UPPER);

        $content = $request->rawContent() ?: null;

        $illuminate_request = new IlluminateRequest($get, $post, []/* attributes */, $cookie, $files, $server, $content);

        return $illuminate_request;
    }

    private static function dealWithResponse($response, $illuminate_response, $accept_gzip)
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
            if (strlen($content) > static::$gzip_min_length && is_mime_gzip($mime)) {
                // $response->gzip(static::$gzip);
            }
        }
        // send content & close
        $response->end($content);
    }

    protected static function dealWithPublic($request, $response)
    {
        static $public_path;
        if (!$public_path) {
            $app = static::$app;
            $public_path = $app->make('path.public');

        }
        $uri = $request->server['request_uri'];
        $file = realpath($public_path . $uri);
        if (is_file($file)) {
            if (!strncasecmp($file, $uri, strlen($public_path))) {
                $response->status(403);
                $response->end();
            } else {
                $response->header('Content-Type', get_mime_type($file));
                if(!filesize($file)) {
                    $response->end();
                } else {
                    $response->sendfile($file);
                }
            }
            return true;
        }
        return false;

    }

    protected static function getApp()
    {

        $app = new App(static::$root_dir);

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
