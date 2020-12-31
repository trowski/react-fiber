<?php

namespace Trowski\ReactFiber\Examples;

use React\EventLoop\Factory;
use React\Http\Browser;
use React\Promise;
use React\Promise\Stream;
use React\Stream\ReadableStreamInterface;
use RingCentral\Psr7\Response;
use Trowski\ReactFiber\FiberLoop;

require \dirname(__DIR__) . '/vendor/autoload.php';

$loop = new FiberLoop(Factory::create());

$browser = new Browser($loop);

$request = function (string $method, string $url) use ($browser, $loop): void {
    /** @var Response $response */
    $response = $loop->await($browser->requestStreaming($method, $url));

    /** @var ReadableStreamInterface $stream */
    $stream = $response->getBody();

    $body = $loop->await(Stream\buffer($stream));

    var_dump(\sprintf(
        '%s %s; Status: %d; Body length: %d',
        $method,
        $url,
        $response->getStatusCode(),
        \strlen($body)
    ));
};

$requests = [];

$requests[] = $loop->async($request, 'GET', 'https://reactphp.org');
$requests[] = $loop->async($request, 'GET', 'https://google.com');
$requests[] = $loop->async($request, 'GET', 'https://www.php.net');

$loop->await(Promise\all($requests));
