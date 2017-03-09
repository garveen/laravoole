<?php
namespace Laravoole;

class Server
{

    protected $wrapper;

    public function __construct($wrapper)
    {
        $this->wrapper = Util::checkWrapper($wrapper);
    }

    public function getWrapper()
    {
        return $this->wrapper;
    }

    public function start($host, $port, $pid_file, $root_dir, $handler_config, $wrapper_config)
    {
        require __DIR__ . '/Mime.php';
        $wrapper = new $this->wrapper($host, $port);
        $wrapper->init($pid_file, $root_dir, $handler_config, $wrapper_config);
        $wrapper->start();
    }

}
