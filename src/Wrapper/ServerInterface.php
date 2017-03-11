<?php
namespace Laravoole\Wrapper;

interface ServerInterface
{
    public static function getParams();

    public function __construct($host, $port);
    /**
     * event callback
     * @param  string   $event    start receive shutdown WorkerStart close request
     * @param  callable $callback event handler
     */
    public function on($event, callable $callback);
    public function start();
    public function send($fd, $content);
    public function close($fd);
    public function getPid();

    /**
     * Normally you did not need to implement this method if your wrapper extends Laravoole\Base
     * @param  array $configs
     * @return null
     */
    public function init(array $configs);
}
