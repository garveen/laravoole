<?php
namespace Laravoole;

use Exception;
use ErrorException;

use Laravoole\Illuminate\Application;
use Laravoole\Illuminate\Request as IlluminateRequest;

use Illuminate\Support\Facades\Facade;
use Psr\Http\Message\ServerRequestInterface;

use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;

abstract class Base
{

    protected $root_dir;

    protected $pid_file;

    protected $handler_config;

    protected $kernel;

    protected $tmp_autoloader;

    protected $app;

    protected $server;

    public function start()
    {
        throw new Exception(__CLASS__ . "::start MUST be implemented", 1);
    }

    final public function init($pid_file, $root_dir, $handler_config, $wrapper_config)
    {
        $this->pid_file = $pid_file;
        $this->root_dir = $root_dir;
        $this->handler_config = $handler_config;
        $this->wrapper_config = $wrapper_config;
    }

    public function prepareKernel()
    {
        // unregister temporary autoloader
        foreach (spl_autoload_functions() as $function) {
            spl_autoload_unregister($function);
        }

        require $this->root_dir . '/bootstrap/autoload.php';
        $this->app = $this->getApp();

        $this->kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        // from \Illuminate\Contracts\Console\Kernel
        // do not using Http\Kernel here, because needs SetRequestForConsole
        $this->app->bootstrapWith([
            'Illuminate\Foundation\Bootstrap\DetectEnvironment',
            'Illuminate\Foundation\Bootstrap\LoadConfiguration',
            'Illuminate\Foundation\Bootstrap\ConfigureLogging',
            'Illuminate\Foundation\Bootstrap\HandleExceptions',
            'Illuminate\Foundation\Bootstrap\RegisterFacades',
            'Illuminate\Foundation\Bootstrap\SetRequestForConsole',
            'Illuminate\Foundation\Bootstrap\RegisterProviders',
            'Illuminate\Foundation\Bootstrap\BootProviders',
        ]);
        chdir(public_path());
    }

    public function onRequest($request, $illuminate_request = false)
    {
        $psrRequest = $this->convertRequest($request);
        return $this->onPsrRequest($psrRequest, $illuminate_request);
    }

    protected function convertRequest($request)
    {
        if($request instanceof ServerRequestInterface) {
            return $request;
        } else {
            throw new Exception("not implemented", 1);

        }
    }

    public function onPsrRequest(ServerRequestInterface $psrRequest, $illuminate_request = false)
    {
        // for file system
        clearstatcache();
        if (config('laravoole.base_config.deal_with_public')) {
            if ($response = $this->dealWithPublic($psrRequest->getUri())) {
                return $response;
            }
        }

        try {
            $kernel = $this->kernel;

            if (!$illuminate_request) {
                $illuminate_request = IlluminateRequest::createFromBase((new HttpFoundationFactory)->createRequest($psrRequest));
            }
            $this->app['events']->fire('laravoole.on_request', [$illuminate_request]);

            $illuminate_response = $kernel->handle($illuminate_request);
            // Is gzip enabled and the client accept it?
            $accept_gzip = config('laravoole.base_config.gzip') && stripos($psrRequest->getHeaderLine('Accept-Encoding'), 'gzip') !== false;

            $response = (new DiactorosFactory)->createResponse($illuminate_response);

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

            if ($this->app->isProviderLoaded(\Illuminate\Auth\AuthServiceProvider::class)) {
                $this->app->register(\Illuminate\Auth\AuthServiceProvider::class, [], true);
                Facade::clearResolvedInstance('auth');
            }

        }
        $response->accpetGzip = $accept_gzip;
        return $response;

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

        $content = $request->getRawContent() ?: null;

        return new $classname($get, $post, []/* attributes */, $cookie, $files, $server, $content);
    }



    public function endResponse($responseCallback, $content)
    {
        // send content & close
        $responseCallback->end($content);
    }

    protected function dealWithPublic($uri, $responseCallback)
    {
        static $public_path;
        if (!$public_path) {
            $app = $this->app;
            $public_path = $app->make('path.public');

        }
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
        $app = new Application($this->root_dir);
        $rootNamespace = $app->getNamespace();
        $rootNamespace = trim($rootNamespace, '\\');

        $app->singleton(
            \Illuminate\Contracts\Http\Kernel::class,
            "\\{$rootNamespace}\\Http\\Kernel"
        );

        $app->singleton(
            \Illuminate\Contracts\Console\Kernel::class,
            "\\{$rootNamespace}\\Console\\Kernel"
        );

        $app->singleton(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            "\\{$rootNamespace}\\Exceptions\\Handler"
        );

        return $app;
    }

}
