<?php
namespace Laravoole;

use Exception;
use ErrorException;

use swoole_http_request;

use Laravoole\Illuminate\Application;
use Laravoole\Illuminate\Request as IlluminateRequest;

use Illuminate\Support\Facades\Facade;
use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;
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

    public function onRequest($request, $response)
    {
        throw new Exception("not implemented", 1);

    }

    public function handleRequest($request, IlluminateRequest $illuminate_request = null)
    {
        clearstatcache();

        $kernel = $this->kernel;

        try {

            ob_start();

            if (!$illuminate_request) {
                if ($request instanceof ServerRequestInterface) {
                    $request = (new HttpFoundationFactory)->createRequest($request);
                    $illuminate_request = IlluminateRequest::createFromBase($request);
                } elseif ($request instanceof swoole_http_request) {
                    $illuminate_request = $this->convertRequest($request);
                } else {
                    $illuminate_request = IlluminateRequest::createFromBase($request);
                }
            }

            $this->app['events']->fire('laravoole.on_request', [$illuminate_request]);

            $illuminate_response = $kernel->handle($illuminate_request);

            $content = $illuminate_response->getContent();

            if (strlen($content) === 0 && ob_get_length() > 0) {
                $illuminate_response->setContent(ob_get_contents());
            }

            ob_end_clean();


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
            $this->app['events']->fire('laravoole.on_requested', [$illuminate_request, $illuminate_response]);

            $this->clean($illuminate_request);

        }
        return $illuminate_response;

    }

    public function onPsrRequest(ServerRequestInterface $psrRequest)
    {
        $illuminate_response = $this->handleRequest($psrRequest);
        return (new DiactorosFactory)->createResponse($illuminate_response);

    }

    protected function convertRequest($request, $classname = IlluminateRequest::class)
    {

        $get = isset($request->get) ? $request->get : [];
        $post = isset($request->post) ? $request->post : [];
        $cookie = isset($request->cookie) ? $request->cookie : [];
        $server = isset($request->server) ? $request->server : [];
        $header = isset($request->header) ? $request->header : [];
        $files = isset($request->files) ? $request->files : [];
        // $attr = isset($request->files) ? $request->files : [];

        $content = $request->rawContent() ?: null;

        return new $classname($get, $post, []/* attributes */, $cookie, $files, $server, $content);
    }

    protected function clean(IlluminateRequest $request)
    {
        if ($request->hasSession()) {
            $request->getSession()->clear();
        }

        // Clean laravel cookie queue
        $cookies = $this->app->make(CookieJar::class);
        foreach ($cookies->getQueuedCookies() as $name => $cookie) {
            $cookies->unqueue($name);
        }

        if ($this->app->isProviderLoaded(\Illuminate\Auth\AuthServiceProvider::class)) {
            $this->app->register(\Illuminate\Auth\AuthServiceProvider::class, [], true);
            Facade::clearResolvedInstance('auth');
        }
    }

    public function endResponse($responseCallback, $content)
    {
        // send content & close
        $responseCallback->end($content);
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
