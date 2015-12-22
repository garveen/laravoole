<?php
namespace Laravoole\Wrapper;

use Workerman\Worker;

class WorkermanFastCGIWrapper implements ServerInterface
{
    static $params = [
        'name',
        'user',
        'reloadable',
        'transport',
        'daemonize',
        'stdoutFile',
        'pidFile',
        'reusePort',
    ];

    static $defaults = [];

    protected $server;
    protected $eventMapper = [
        'WorkerStart' => 'onWorkerStart',
        'WorkerStop' => 'onWorkerStop',
        'Connect' => 'onConnect',
        'Receive' => 'onMessage',
        'Close' => 'onClose',
    ];
    protected $onStart;
    public function __construct($host, $port)
    {
        require dirname(COMPOSER_INSTALLED) . '/workerman/workerman/Autoloader.php';
        $this->server = new Worker("tcp://{$host}:{$port}");
    }

    public function on($event, callable $callback)
    {
        if (!isset($this->eventMapper[$event])) {
            throw new \Exception("Event $event not exists", 1);
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

    public function start()
    {
        return $this->server->runAll();
    }

    public function send($fd, $content)
    {
        return $this->server->connections[$fd]->send($content);
    }

    public function close($fd)
    {
        return $this->server->connections[$fd]->close();
    }

    public function getPid()
    {
        throw new \Exception("Can't read pid from Workerman", 1);

    }
}
