<?php
namespace Laravoole\Wrapper;

interface ServerInterface
{
    public function on($event, callable $callback);
    public function set($settings);
    public function start();
    public function send($fd, $content);
    public function close($fd);
    public function getPid();
}
