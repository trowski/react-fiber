<?php

namespace Trowski\ReactFiber\Examples;

use React\EventLoop\Factory;
use React\Promise;
use Trowski\ReactFiber\FiberLoop;
use function Trowski\ReactFiber\await;

require \dirname(__DIR__) . '/vendor/autoload.php';

$loop = new FiberLoop(Factory::create());

$value = await(Promise\resolve('Promise resolution value'), $loop);

var_dump($value);

try {
    $value = await(Promise\reject(new \Exception('Rejection reason')), $loop);
} catch (\Throwable $exception) {
    var_dump($exception);
}
