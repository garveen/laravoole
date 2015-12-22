<?php
namespace Laravoole\Wrapper;

interface ServerInterface
{
    /**
     * event callback
     * @param  string   $event    start receive shutdown WorkerStart close request
     * @param  callable $callback event handler
     */
    public function on($event, callable $callback);
    public function start($config, $settings);
    public function send($fd, $content);
    public function close($fd);
    public function getPid();
}
