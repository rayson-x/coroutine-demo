<?php
namespace Coroutine\Socket;

use Ant\Http\Request;
use Ant\Http\Response;
use Coroutine\Loop\Scheduler;
use Coroutine\Loop\SystemCall;

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
            $connection = new Connection($clientSocket);
            yield newTask($this->handleBuffer($connection));
        }
    }

    protected function handleBuffer(Connection $connection)
    {
        // Todo::连接池,保存socket句柄,每次有input都创建一个新任务去处理
        // Todo::监听句柄不退出任务队列
        // Todo::连接超时,一定时间内没进行IO的连接自动关闭

        $buffer = '';
        while(true) {
            if($connection->isClose()) {
                $connection->close();
                yield endTask();
                break;
            }

            if(false !== stripos($buffer,"\r\n\r\n")) {
                list($header) = explode("\r\n\r\n",$buffer,2);

                $packLen = strlen($buffer);
                if (substr($header,0,3) != 'GET' && preg_match("/\r\nContent-Length: ?(\d+)/i", $header, $match)) {
                    $packLen = $match[1] + $packLen + 4;
                }

                if($packLen <= strlen($buffer)){
                    // 接收到一个完整的Http请求
                    yield newTask($this->handleData($connection,substr($buffer,0,$packLen)));
                    $buffer = substr($buffer,$packLen + 1);
                }
            }

            yield waitForRead($connection->getSocket());
            $buffer .= $connection->read(8092);
        }
    }

    /**
     * @param $connection
     * @param $buffer
     * @return \Generator
     */
    protected function handleData(Connection $connection,$buffer)
    {
        $start = microtime(true);
        $request = Request::createFromRequestStr($buffer);
        $response = Response::prepare($request)->keepImmutability(false);

        if($this->onMessage) {
            $result = call_user_func($this->onMessage,$request,$response);
            if($result instanceof \Generator) {
                yield from $result;
            }
        }

        $response->addHeaderFromIterator([
            'x-run-time' => (((microtime(true) - $start) * 10000)/10).'ms',
            'server' => 'coroutine-framework',
            'connection' => 'keep-alive',
            'content-length' => $response->getBody()->getSize(),
        ]);

        $connection->write((string)$response);
    }
}