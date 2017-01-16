<?php
use Coroutine\Loop\Scheduler;
use Coroutine\Socket\HttpServer;

include "vendor/autoload.php";

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