<?php
namespace Laravoole\Wrapper;

use Laravoole\Base;
use Exception;
use Workerman\Worker;

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

    public function __construct($host, $port)
    {
        if (file_exists(__DIR__ . '/../../vendor/workerman/workerman/Autoloader.php')) {
            require __DIR__ . '/../../vendor/workerman/workerman/Autoloader.php';
        } else {
            require __DIR__ . '/../../../../workerman/workerman/Autoloader.php'; // @codeCoverageIgnore
        }
    }

    public function start()
    {
        $this->set(['pidFile' => $this->pid_file]);
        if (!empty($this->handler_config)) {
            $this->set($this->handler_config);
        }
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
            throw new Exception("Event $event not exists", 1); // @codeCoverageIgnore
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

    /**
     * @codeCoverageIgnore
     */
    public function getPid()
    {
        throw new Exception("Can't read pid from Workerman", 1);
    }

}
