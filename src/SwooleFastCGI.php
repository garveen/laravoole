<?php
namespace Laravoole;

use swoole_server;
use Exception;
use Laravoole\Wrapper\SwooleFastCGIWrapper;

class SwooleFastCGI extends SwooleHttp
{

    use Protocol\FastCGI;
    const WRAPPER = SwooleFastCGIWrapper::class;

    public static function start($config, $settings)
    {
        // override
        $config['deal_with_public'] = false;
        parent::init($config);
        static::$settings = $settings;

        static::$server = new SwooleFastCGIWrapper(static::$host, static::$port);

        if (!empty(static::$settings)) {
            static::$server->set(static::$settings);
        }
        static::$server->on('Start', [static::class, 'onServerStart']);
        static::$server->on('Receive', [static::class, 'onReceive']);
        static::$server->on('Shutdown', [static::class, 'onServerShutdown']);
        static::$server->on('WorkerStart', [static::class, 'onWorkerStart']);

        require __DIR__ . '/Mime.php';

        static::$server->start();
    }

    public static function onReceive($serv, $fd, $from_id, $data)
    {
        return static::receive($fd, $data);
    }

    protected static function send($fd, $content)
    {
        return static::$server->send($fd, $content);
    }

    protected static function close($fd)
    {
        return static::$server->close($fd);
    }

}
