<?php
namespace Laravoole;

use Laravoole\Wrapper\SwooleWebSocketWrapper;
use Laravoole\Wrapper\IlluminateRequestWrapper;
use swoole_websocket_server;


class SwooleWebSocket extends Base
{
    const WRAPPER = SwooleWebSocketWrapper::class;

    protected static $connections = [];

    protected static function init($config)
    {
        static::$host = $config['host'];
        static::$port = $config['port'];
        static::$pid_file = $config['pid_file'];
        static::$root_dir = $config['root_dir'];
    }

    public static function start($config, $settings)
    {
        static::init($config);
        static::$settings = $settings;

        static::$server = new SwooleWebSocketWrapper(static::$host, static::$port);

        if (!empty(static::$settings)) {
            static::$server->set(static::$settings);
        }
        static::$server->on('Start', [static::class, 'onServerStart']);
        static::$server->on('Shutdown', [static::class, 'onServerShutdown']);
        static::$server->on('WorkerStart', [static::class, 'onWorkerStart']);
        static::$server->on('Open', [static::class, 'onOpen']);
        static::$server->on('Message', [static::class, 'onMessage']);
        static::$server->on('Close', [static::class, 'onClose']);

        static::$server->start();
    }

    public static function onServerStart()
    {
        // put pid
        file_put_contents(
            static::$pid_file,
            static::$server->getPid()
        );
    }

    public static function onWorkerStart($serv, $worker_id)
    {
        static::$server = $serv;
        // unregister temporary autoloader
        foreach (spl_autoload_functions() as $function) {
            spl_autoload_unregister($function);
        }

        require static::$root_dir . '/bootstrap/autoload.php';
        static::$app = static::getApp();
        // static::$app->boot();
        // var_dump(static::$app->isBooted());

        static::$kernel = static::$app->make(\Illuminate\Contracts\Http\Kernel::class);

    }

    public static function onServerShutdown($serv)
    {
        unlink(static::$pid_file);
    }

    public static function onOpen(swoole_websocket_server $server, $request) {
        $laravooleRequest = new Request($request->fd);
        foreach($request as $k => $v) {
            $laravooleRequest->$k = $v;
        }
        static::$connections[$request->fd] = $laravooleRequest;
        $server->push($request->fd, getpid());


    }

    public static function onMessage(swoole_websocket_server $server, $frame) {
        $request = static::$connections[$frame->fd];

        $data = json_decode($frame->data);

        $request->request_uri = $data->m;
        $request->get = (array)($data->p);

        $illuminateRequest = static::dealWithRequest($request, IlluminateRequestWrapper::class);

        $response = new Response(static::class, $request);
        static::onRequest($request, $response, $illuminateRequest);

    }

    public static function endResponse($response, $content)
    {
        static::$server->push($response->request->fd, $content);

    }

    public static function response($request, $out) {
        fwrite(STDOUT, $out);
        static::$server->push($request->fd, $out);
    }

    public static function onClose($server, $fd) {
        unset(static::$connections[$fd]);
    }

}
