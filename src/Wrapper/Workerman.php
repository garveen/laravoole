<?php
namespace Laravoole\Wrapper;

use Laravoole\Base;
use Exception;

abstract class Workerman extends Base implements ServerInterface
{
    protected $eventMapper = [
        'WorkerStart' => 'onWorkerStart',
        'WorkerStop' => 'onWorkerStop',
        'Connect' => 'onConnect',
        'Receive' => 'onMessage',
        'Close' => 'onClose',
    ];

    public static function getParams()
    {
        return [
            'name',
            'user',
            'reloadable',
            'transport',
            'daemonize',
            'stdoutFile',
            'pidFile',
            'reusePort',
        ];
    }

    public static function getDefaults()
    {
        return [];
    }

    public function start($config, $settings)
    {
        $this->host = $config['host'];
        $this->port = $config['port'];
        $this->pid_file = $config['pid_file'];
        $this->root_dir = $config['root_dir'];
        $this->gzip = $config['gzip'];
        $this->gzip_min_length = $config['gzip_min_length'];

        $this->settings = $settings;

        if (!empty($this->settings)) {
            $this->set($this->settings);
        }
        $this->on('Receive', [$this, 'onReceive']);
        $this->on('WorkerStart', [$this, 'onWorkerStart']);

        return $this->server->runAll();
    }

    public function send($fd, $content)
    {
        return $this->connections[$fd]['connection']->send($content);
    }

    public function close($fd)
    {
        return $this->connections[$fd]['connection']->close();
    }

    public function onWorkerStart($worker)
    {
        foreach (spl_autoload_functions() as $function) {
            spl_autoload_unregister($function);
        }

        $this->server = $worker;
        require $this->root_dir . '/bootstrap/autoload.php';
        $this->app = $this->getApp();

        $this->kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
    }

    public function on($event, callable $callback)
    {
        if (!isset($this->eventMapper[$event])) {
            throw new Exception("Event $event not exists", 1);
        }

        $this->server->{$this->eventMapper[$event]} = $callback;
        return true;
    }

    public function set($settings)
    {
        $server = $this->server;
        foreach ($settings as $key => $value) {
            $server::$$key = $value;
        }
        return true;
    }

    public function getPid()
    {
        throw new Exception("Can't read pid from Workerman", 1);

    }

}
