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
            'reusePort',
        ];
    }

    public function start()
    {
        $this->set(['pidFile' => $this->pid_file]);
        if (!empty($this->handler_config)) {
            $this->set($this->handler_config);
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
        $this->server = $worker;
        parent::prepareKernel();
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
