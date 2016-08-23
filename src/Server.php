<?php
namespace Laravoole;

use Exception;

class Server
{

    protected $wrapper;

    public function __construct($wrapper)
    {
        $class = "Laravoole\\Wrapper\\{$wrapper}Wrapper";
        $this->wrapper = $class;
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
