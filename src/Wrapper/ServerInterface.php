<?php
namespace Laravoole\Wrapper;

interface ServerInterface
{
    public static function getParams();
    public static function getDefaults();
    /**
     * event callback
     * @param  string   $event    start receive shutdown WorkerStart close request
     * @param  callable $callback event handler
     */
    public function on($event, callable $callback);
    public function start();
    public function init($config, $settings);
    public function send($fd, $content);
    public function close($fd);
    public function getPid();
}
