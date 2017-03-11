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

    protected $diactorosFactory;

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

        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require __DIR__ . '/../vendor/autoload.php';
        } else {
            require $this->root_dir . '/bootstrap/autoload.php';
        }
        $this->app = $this->getApp();

        if (isset($this->wrapper_config['environment_path'])) {
            var_dump($this->wrapper_config['environment_path']);
            $this->app->useEnvironmentPath($this->wrapper_config['environment_path']);
        }

        $this->kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $virus = function() {
            // Insert bofore BootProviders
            array_splice($this->bootstrappers, -1, 0, [\Illuminate\Foundation\Bootstrap\SetRequestForConsole::class]);
        };
        $virus = \Closure::bind($virus, $this->kernel, $this->kernel);
        $virus();

        $this->kernel->bootstrap();
        chdir(public_path());
        $this->events = $this->app['events'];
        $this->events->fire('laravoole.bootstraped', [$this->app, $this->kernel]);
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

            $this->events->fire('laravoole.requesting', [$illuminate_request]);

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
            $this->events->fire('laravoole.requested', [$illuminate_request, $illuminate_response]);

            $this->clean($illuminate_request);

        }

        return $illuminate_response;

    }

    public function onPsrRequest(ServerRequestInterface $psrRequest)
    {
        $illuminate_response = $this->handleRequest($psrRequest);
        if (!$this->diactorosFactory) {
            $this->diactorosFactory = new DiactorosFactory;
        }
        return $this->diactorosFactory->createResponse($illuminate_response);

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
            $session = $request->getSession();
            if (is_callable([$session, 'clear'])) {
                $session->clear();
            } else {
                $session->flush();
            }
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

    public function endResponse($response, $content)
    {
        if (!is_string($content)) {
            $response->sendfile($content());
        } else {
            // send content & close
            $response->end($content);
        }
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
