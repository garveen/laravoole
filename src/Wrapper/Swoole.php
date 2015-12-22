<?php
namespace Laravoole\Wrapper;

use swoole_http_server;

use Laravoole\Base;
use Exception;

class Swoole extends Base implements ServerInterface
{

    // http://wiki.swoole.com/wiki/page/274.html
    public static function getParams()
    {
        return $params = [
            'reactor_num',
            'worker_num',
            'max_request',
            'max_conn',
            'task_worker_num',
            'task_ipc_mode',
            'task_max_request',
            'task_tmpdir',
            'dispatch_mode',
            'message_queue_key',
            'daemonize',
            'backlog',
            'log_file',
            'heartbeat_check_interval',
            'heartbeat_idle_time',
            'open_eof_check',
            'open_eof_split',
            'package_eof',
            'open_length_check',
            'package_length_type',
            'package_max_length',
            'open_cpu_affinity',
            'cpu_affinity_ignore',
            'open_tcp_nodelay',
            'tcp_defer_accept',
            'ssl_cert_file',
            'ssl_method',
            'user',
            'group',
            'chroot',
            'pipe_buffer_size',
            'buffer_output_size',
            'enable_unsafe_event',
            'discard_timeout_request',
            'enable_reuse_port',
        ];
    }

    public static function getDefaults()
    {
        return [
            'log_file' => [self::class, 'getLogFile'],
            'daemonize' => 1,
            'max_request' => 2000,
        ];
    }

    protected function init($config)
    {
        $this->host = $config['host'];
        $this->port = $config['port'];
        $this->pid_file = $config['pid_file'];
        $this->root_dir = $config['root_dir'];
        $this->deal_with_public = $config['deal_with_public'];
        $this->gzip = $config['gzip'];
        $this->gzip_min_length = $config['gzip_min_length'];

    }
    public function onServerStart()
    {
        // put pid
        file_put_contents(
            $this->pid_file,
            $this->getPid()
        );
    }

    public function onWorkerStart($serv, $worker_id)
    {
        // unregister temporary autoloader
        foreach (spl_autoload_functions() as $function) {
            spl_autoload_unregister($function);
        }

        require $this->root_dir . '/bootstrap/autoload.php';
        $this->app = $this->getApp();

        $this->kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);

    }

    public function onServerShutdown($serv)
    {
        unlink($this->pid_file);
    }

    public static function getLogFile()
    {
        return app()->storagePath() . '/logs/laravoole.log';
    }

    public function on($event, callable $callback)
    {
        return $this->server->on($event, $callback);
    }

    public function send($fd, $content)
    {
        return $this->server->send($fd, $content);
    }

    public function close($fd)
    {
        return $this->server->close($fd);
    }

    public function getPid()
    {
        return $this->server->master_pid;
    }

}
