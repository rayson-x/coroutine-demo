<?php
namespace Coroutine\Socket;

class Connection
{
    protected $socket;
    protected $close = false;

    public function __construct($socket)
    {
        if(!is_resource($socket)){
            throw new \InvalidArgumentException;
        }

        $this->socket = $socket;
        stream_set_blocking($this->socket, 0);
    }

    public function read($len)
    {
        return stream_get_contents($this->socket, $len);
    }

    public function write($data)
    {
        return fwrite($this->socket,$data);
    }

    public function close()
    {
        if(!$this->close){
            fclose($this->socket);
            $this->close = true;
        }
    }

    public function isClose()
    {
        return $this->close || feof($this->socket);
    }

    public function getSocket()
    {
        return $this->socket;
    }
}