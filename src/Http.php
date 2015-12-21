<?php
namespace Laravoole;

use Laravoole\Wrapper\SwooleHttp;

class Http extends Base
{
    const WRAPPER = SwooleHttp::class;
    protected function init($config)
    {
        static::$host = $config['host'];
        static::$port = $config['port'];
        static::$pid_file = $config['pid_file'];
        static::$root_dir = $config['root_dir'];
        static::$deal_with_public = $config['deal_with_public'];
        static::$gzip = $config['gzip'];
        static::$gzip_min_length = $config['gzip_min_length'];

    }

    public static function start($config, $settings)
    {
        static::init($config);
        static::$settings = $settings;

        static::$server = new SwooleHttp(static::$host, static::$port);

        if (!empty(static::$settings)) {
            static::$server->set(static::$settings);
        }
        static::$server->on('Start', [static::class, 'onServerStart']);
        static::$server->on('Shutdown', [static::class, 'onServerShutdown']);
        static::$server->on('WorkerStart', [static::class, 'onWorkerStart']);
        static::$server->on('Request', [static::class, 'onRequest']);

        require __DIR__ . '/Mime.php';

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
        // unregister temporary autoloader
        foreach (spl_autoload_functions() as $function) {
            spl_autoload_unregister($function);
        }

        require static::$root_dir . '/bootstrap/autoload.php';
        static::$app = static::getApp();

        static::$kernel = static::$app->make(\Illuminate\Contracts\Http\Kernel::class);

    }

    public static function onServerShutdown($serv)
    {
        unlink(static::$pid_file);
    }

}
