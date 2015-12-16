<?php
namespace Laravoole;

class Request
{

    public $fd;
    public $id;

    public $time;


    public $remote_ip;


    public $remote_port;

    public $get = array();
    public $post = array();
    public $file = array();
    public $cookie = array();
    public $session = array();
    public $server = array();

    public $attrs;

    public $head = array();
    public $body;
    public $meta = array();

    public $finish = false;
    public $ext_name;
    public $status;

    protected $rawContent;

    public $tmpFile;
    protected $fp;

    public function __construct($fd, $id)
    {
        $this->fd = $fd;
        $this->id = $id;
        $this->attrs = new RequestAttrs;
    }


    public function rawContent()
    {
        return $this->rawContent;
    }

    public function setRawContent($content)
    {
        if ($this->fp) {
            fwrite($this->fp, $content);
            return;
        }
        $this->rawContent .= $content;
        return;
        //TODO
        if (strlen($this->rawContent) > 1048576) {
            // make a cache file
        }
    }

    public function __destruct()
    {
        if ($this->fp) {
            fclose($this->fp);
            unlink($this->tmpFile);
        }
    }
}
