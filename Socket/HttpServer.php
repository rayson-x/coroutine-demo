<?php
namespace Coroutine\Socket;

use Ant\Http\Request;
use Ant\Http\Response;
use Coroutine\Loop\Scheduler;

class HttpServer
{
    public $onMessage;

    protected $scheduler;

    public function __construct(Scheduler $scheduler)
    {
        $this->scheduler = $scheduler;
    }

    public function listen($port, $host = '0.0.0.0')
    {
        $socket = @stream_socket_server("tcp://{$host}:{$port}", $errNo, $errStr);

        if (!$socket) {
            throw new \Exception($errStr, $errNo);
        }

        stream_set_blocking($socket, 0);

        $this->scheduler->newTask($this->handleConnection($socket));
        $this->scheduler->ioTask();
    }

    protected function handleConnection($socket)
    {
        while (true) {
            yield waitForRead($socket);
            $clientSocket = stream_socket_accept($socket, 0);
            stream_set_blocking($clientSocket, 0);
            yield newTask($this->handleData($clientSocket));
        }
    }

    protected function handleData($socket)
    {
        // Todo::监听句柄不退出任务队列
        // Todo::连接超时,一定时间内没进行IO的连接自动关闭
        $buffer = '';
        do{
            yield waitForRead($socket);
            $buffer .= fread($socket, 8192);
        } while(false === stripos($buffer,"\r\n\r\n"));

        $start = microtime(true);
        $request = Request::createFromRequestStr($buffer);
        $response = Response::prepare($request);
        $response->keepImmutability(false);

        if($this->onMessage) {
            $result = call_user_func($this->onMessage,$request,$response);
            if($result instanceof \Generator) {
                do{
                    $value = (yield $result->current());
                    $result->send($value);
                } while($result->valid());
            }
        } else {
            $response->write("hello world");
        }

        $response->withHeader('x-run-time',(int)((microtime(true) - $start) * 1000).'ms');
        yield waitForWrite($socket);
        fwrite($socket, (string)$response);
        fclose($socket);
    }
}