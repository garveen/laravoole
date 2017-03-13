<?php
namespace Laravoole;

use Exception;
use ReflectionClass;

use Laravoole\Wrapper\ServerInterface;

class Server
{
    protected $wrapper;

    protected $startingCallbacks = [];

    public function __construct($wrapper, $wrapper_file = '')
    {
        if (!class_exists($wrapper)) {
            require $wrapper_file;
        }
        $ref = new ReflectionClass($wrapper);
        if(!$ref->implementsInterface(ServerInterface::class)) {
            throw new Exception("$wrapper must be instance of Laravoole\\Wrapper\\ServerInterface", 1);
        }

        $this->wrapper = $wrapper;
    }

    public function getWrapper()
    {
        return $this->wrapper;
    }

    public function start($configs)
    {
        require __DIR__ . '/Mime.php';
        $wrapper = new $this->wrapper($configs['host'], $configs['port']);
        $wrapper->init($configs);
        return $wrapper->start();
    }

}
