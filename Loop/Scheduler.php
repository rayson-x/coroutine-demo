<?php
namespace Coroutine\Loop;

use SplQueue;
use Generator;

/**
 * Class Scheduler
 * @package Coroutine
 */
class Scheduler
{
    protected $taskQueue;
    protected $taskMap = [];
    protected $maxTaskId = 0;
    protected $waitingForRead = [];
    protected $waitingForWrite = [];

    public function __construct()
    {
        $this->taskQueue = new SplQueue();
    }

    /**
     * @param $socket
     * @param Task $task
     */
    public function waitForRead($socket, Task $task)
    {
        if (isset($this->waitingForRead[(int) $socket])) {
            $this->waitingForRead[(int) $socket][1][] = $task;
        } else {
            $this->waitingForRead[(int) $socket] = [$socket, [$task]];
        }
    }

    /**
     * @param $socket
     * @param Task $task
     */
    public function waitForWrite($socket, Task $task)
    {
        if (isset($this->waitingForWrite[(int) $socket])) {
            $this->waitingForWrite[(int) $socket][1][] = $task;
        } else {
            $this->waitingForWrite[(int) $socket] = [$socket, [$task]];
        }
    }

    /**
     * @param $timeout
     */
    protected function ioPoll($timeout)
    {
        if(empty($this->waitingForRead) && empty($this->waitingForWrite)){
            return;
        }

        $rSocks = [];
        $wSocks = [];
        $eSocks = [];

        //获取等待中的读任务
        foreach ($this->waitingForRead as list($socket)) {
            $rSocks[] = $socket;
        }

        //获取等待中的写任务
        foreach ($this->waitingForWrite as list($socket)) {
            $wSocks[] = $socket;
        }

        //获取已经准备好的任务
        if (!stream_select($rSocks, $wSocks, $eSocks, $timeout)) {
            return;
        }

        // 切换上下文至IO已经准备好的任务中
        foreach ($rSocks as $socket) {
            // 获取上下文列表
            list(, $tasks) = $this->waitingForRead[(int) $socket];
            // 清除已经准备好的IO
            unset($this->waitingForRead[(int) $socket]);

            foreach ($tasks as $task) {
                $this->schedule($task);
            }
        }

        foreach ($wSocks as $socket) {
            list(, $tasks) = $this->waitingForWrite[(int) $socket];
            unset($this->waitingForWrite[(int) $socket]);

            foreach ($tasks as $task) {
                $this->schedule($task);
            }
        }
    }

    /**
     * @param Generator $coroutine
     * @return int
     */
    public function newTask(Generator $coroutine)
    {
        $tid = ++$this->maxTaskId;
        $task = new Task($tid, $coroutine);
        $this->taskMap[$tid] = $task;
        $this->schedule($task);
        return $tid;
    }

    /**
     * @param Task $task
     */
    public function schedule(Task $task)
    {
        $this->taskQueue->enqueue($task);
    }

    public function ioTask()
    {
        $task = function() {
            while(true){
                $this->ioPoll($this->taskQueue->isEmpty() ? null : 0);
                yield;
            }
        };

        $this->newTask($task());
    }

    public function run()
    {
        while (!$this->taskQueue->isEmpty()) {
            $task = $this->taskQueue->dequeue();
            $retval = $task->run();

            if ($retval instanceof SystemCall) {
                $retval($task, $this);
                continue;
            }

            if ($task->isFinished()) {
                unset($this->taskMap[$task->getTaskId()]);
            } else {
                // 添加到队列尾部
                $this->schedule($task);
            }
        }
    }
}