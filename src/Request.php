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
    public $files = array();
    public $cookie = array();
    public $server = array();

    public $attrs;

    public $head = array();
    public $body;
    public $meta = array();

    public $finish = false;
    public $ext_name;
    public $status;

    public $remainder = '';

    protected $rawContent;

    public $tmpFile;
    protected $fp;
    protected $post_max_size = 0;
    protected $tmp_dir;
    protected $post_len = 0;

    public $response;

    public function __construct($fd)
    {
        $this->post_max_size = $this->return_bytes(ini_get('post_max_size'));
        $this->fd = $fd;
        $this->attrs = new RequestAttrs;

        return;

        $this->file_uploads = ini_get('file_uploads');
    }

    public function rawContent()
    {
        if ($this->tmpFile) {
            return file_get_contents($this->tmpFile);
        } else {
            return $this->rawContent;
        }
    }

    public function setRawContent($content)
    {
        if($this->attrs->inputDone) {
            return;
        }
        $this->post_len += strlen($content);
        if ($this->post_len > $this->post_max_size) {
            $this->finishRawContent();
            return;
        }
        if ($this->fp) {
            fwrite($this->fp, $content);
            return;
        }
        $this->rawContent .= $content;

        // write to file when post > 2M
        if (strlen($this->rawContent) > 2097152) {
            $this->tmpFile = tempnam($this->getTempDir(), 'laravoole_');
            $this->fp = fopen($this->tmpFile, 'w');
            fwrite($this->fp, $this->rawContent);
        }
    }

    public function finishRawContent()
    {
        if ($this->fp) {
            fclose($this->fp);
            $this->fp = null;
        }
        $this->attrs->inputDone = true;
    }

    public function finishParams()
    {
        if (!isset($this->server['REQUEST_TIME'])) {
            $this->server['REQUEST_TIME'] = time();
        }
        if (!isset($this->server['REQUEST_TIME_FLOAT'])) {
            $req->server['REQUEST_TIME_FLOAT'] = microtime(true);
        }
        $this->attrs->paramsDone = true;
    }

    public function destoryTempFiles()
    {
        if ($this->fp) {
            fclose($this->fp);
            $this->fp = null;
        }
        if($this->tmpFile && file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
            $this->tmpFile = null;
        }
        foreach ($this->files as $file) {
            if (isset($file['tmp_name'])) {
                $name = $file['tmp_name'];
                if(file_exists($name)) {
                    unlink($name);
                }
            }
        }
    }

    public function __destruct()
    {
        $this->destoryTempFiles();
    }

    public function getTempDir()
    {
        if (!$this->tmp_dir) {
            $this->tmp_dir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
        }
        return $this->tmp_dir;
    }

    public function return_bytes($val)
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (float) $val;
        switch ($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }
}
