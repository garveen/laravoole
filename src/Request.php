<?php
namespace Laravoole;

class Request
{
    public $fd;

    public function __construct($fd)
    {
        $this->fd = $fd;
    }

    public function rawContent()
    {
        return '';
    }

}
