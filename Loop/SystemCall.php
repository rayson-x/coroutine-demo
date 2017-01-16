<?php
namespace Coroutine\Loop;


class SystemCall
{
    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function __invoke(Task $task, Scheduler $scheduler)
    {
        $callback = $this->callback;
        return $callback($task, $scheduler);
    }

    public static function waitForRead($socket)
    {
        return new SystemCall(
            function(Task $task, Scheduler $scheduler) use ($socket) {
                $scheduler->waitForRead($socket, $task);
            }
        );
    }

    public static function waitForWrite($socket)
    {
        return new SystemCall(
            function(Task $task, Scheduler $scheduler) use ($socket) {
                $scheduler->waitForWrite($socket, $task);
            }
        );
    }

    public static function newTask(\Generator $coroutine)
    {
        return new SystemCall(
            function(Task $task, Scheduler $scheduler) use ($coroutine) {
                $task->setSendValue($scheduler->newTask($coroutine));
                $scheduler->schedule($task);
            }
        );
    }

    public static function endTask()
    {
        return new SystemCall(
            function(Task $task, Scheduler $scheduler) {
//            var_dump($task->getTaskId());
            }
        );
    }
}