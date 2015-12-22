<?php
namespace Laravoole;

use Laravoole\Wrapper\WorkermanFastCGIWrapper;
use Exception;

class WorkermanFastCGI extends Base
{
    use Protocol\FastCGI;

    const WRAPPER = WorkermanFastCGIWrapper::class;

    protected static $pid_file;
    protected static $root_dir;
    protected static $gzip;
    protected static $gzip_min_length;
    protected static $host;
    protected static $port;

    public static function init($config)
    {
        static::$host = $config['host'];
        static::$port = $config['port'];
        static::$pid_file = $config['pid_file'];
        static::$root_dir = $config['root_dir'];
        static::$gzip = $config['gzip'];
        static::$gzip_min_length = $config['gzip_min_length'];

    }

    public static function start($config, $settings)
    {
        static::init($config);
        static::$settings = $settings;
        static::$server = new WorkermanFastCGIWrapper(static::$host, static::$port);

        if (!empty(static::$settings)) {
            static::$server->set(static::$settings);
        }
        static::$server->on('Receive', [static::class, 'onReceive']);
        static::$server->on('WorkerStart', [static::class, 'onWorkerStart']);

        require __DIR__ . '/Mime.php';

        static::$server->start();
    }

    public static function onReceive($connection, $data)
    {
        static::$connections[$connection->id]['connection'] = $connection;
        return static::receive($connection->id, $data);
    }


    protected static function send($fd, $content)
    {
        return static::$connections[$fd]['connection']->send($content);
    }

    protected static function close($fd)
    {
        return static::$connections[$fd]['connection']->close();
    }

    public static function onWorkerStart($worker)
    {
        foreach (spl_autoload_functions() as $function) {
            spl_autoload_unregister($function);
        }

        static::$server = $worker;
        require static::$root_dir . '/bootstrap/autoload.php';
        static::$app = static::getApp();

        static::$kernel = static::$app->make(\Illuminate\Contracts\Http\Kernel::class);
    }

}
