<?php

namespace Trowski\ReactFiber\Examples;

use React\EventLoop\Factory;
use React\Promise;
use Trowski\ReactFiber\FiberLoop;

require \dirname(__DIR__) . '/vendor/autoload.php';

$loop = new FiberLoop(Factory::create());

$value = $loop->await(Promise\resolve('Promise resolution value'));

var_dump($value);

try {
    $value = $loop->await(Promise\reject(new \Exception('Rejection reason')));
} catch (\Throwable $exception) {
    var_dump($exception);
}
