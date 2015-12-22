<?php
namespace Laravoole;

// use Laravoole\Wrapper\WorkermanFastCGIWrapper;
use Exception;

class Server
{
    // use Protocol\FastCGI;

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

    public function start($config, $settings)
    {
        require __DIR__ . '/Mime.php';
        $wrapper = new $this->wrapper($config['host'], $config['port']);
        $wrapper->start($config, $settings);
    }

}
