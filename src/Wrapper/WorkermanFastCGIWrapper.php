<?php
namespace Laravoole\Wrapper;

use Workerman\Worker;
use Laravoole\Protocol\FastCGI;

class WorkermanFastCGIWrapper extends Workerman implements ServerInterface
{
    use FastCGI;

    public function __construct($host, $port)
    {
        require dirname(COMPOSER_INSTALLED) . '/workerman/workerman/Autoloader.php';
        $this->server = new Worker("tcp://{$host}:{$port}");
    }

    public function start()
    {
        $this->config['deal_with_public'] = false;
        parent::start();
    }


    public function onReceive($connection, $data)
    {
        $this->connections[$connection->id]['connection'] = $connection;
        $ret = $this->receive($connection->id, $data);
        return $ret;
    }

}
