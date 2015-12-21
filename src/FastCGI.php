<?php
namespace Laravoole;

use swoole_server;
use Exception;
use Laravoole\Wrapper\Swoole;

class FastCGI extends Http
{
    use Protocol\FastCGI;

    const WRAPPER = Swoole::class;

    public static function start($config, $settings)
    {
        // override
        $config['deal_with_public'] = false;
        parent::init($config);
        static::$settings = $settings;

        static::$server = new Swoole(static::$host, static::$port);

        if (!empty(static::$settings)) {
            static::$server->set(static::$settings);
        }
        static::$server->on('Start', [static::class, 'onServerStart']);
        static::$server->on('Receive', [static::class, 'onReceive']);
        static::$server->on('Shutdown', [static::class, 'onServerShutdown']);
        static::$server->on('WorkerStart', [static::class, 'onWorkerStart']);
        static::$server->on('Close', [static::class, 'onClose']);

        require __DIR__ . '/Mime.php';

        static::$server->start();
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
