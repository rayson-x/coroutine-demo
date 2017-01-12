<?php
use Coroutine\Loop\Task;
use Coroutine\Loop\Scheduler;
use Coroutine\Loop\SystemCall;
use Coroutine\Socket\HttpServer;

include "vendor/autoload.php";

function waitForRead($socket)
{
    return new SystemCall(
        function(Task $task, Scheduler $scheduler) use ($socket) {
            $scheduler->waitForRead($socket, $task);
        }
    );
}

function waitForWrite($socket)
{
    return new SystemCall(
        function(Task $task, Scheduler $scheduler) use ($socket) {
            $scheduler->waitForWrite($socket, $task);
        }
    );
}

function newTask(\Generator $coroutine)
{
    return new SystemCall(
        function(Task $task, Scheduler $scheduler) use ($coroutine) {
            $task->setSendValue($scheduler->newTask($coroutine));
            $scheduler->schedule($task);
        }
    );
}

function endTask()
{
    return new SystemCall(
        function(Task $task, Scheduler $scheduler) {
            var_dump($task->getTaskId());
        }
    );
}

$scheduler = new Scheduler();
$httpServer = new HttpServer($scheduler);
$httpServer->listen(8000);
$httpServer->onMessage = function(\Ant\Http\Request $req, \Ant\Http\Response $res)
{
    $res->setType('json')
        ->setContent(['foo' => 'bar'])
        ->decorate();
};

$scheduler->run();
