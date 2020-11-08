<?php

namespace Trowski\ReactFiber\Examples;

use React\EventLoop\Factory;
use React\Http\Browser;
use React\Promise;
use React\Promise\Stream;
use React\Stream\ReadableStreamInterface;
use RingCentral\Psr7\Response;
use Trowski\ReactFiber\FiberLoop;
use function Trowski\ReactFiber\async;
use function Trowski\ReactFiber\await;

require \dirname(__DIR__) . '/vendor/autoload.php';

$loop = new FiberLoop(Factory::create());

$browser = new Browser($loop);

$request = function (string $method, string $url) use ($browser, $loop): void {
    /** @var Response $response */
    $response = await($browser->requestStreaming($method, $url), $loop);

    /** @var ReadableStreamInterface $stream */
    $stream = $response->getBody();

    $body = await(Stream\buffer($stream), $loop);

    var_dump(\sprintf(
        '%s %s; Status: %d; Body length: %d',
        $method,
        $url,
        $response->getStatusCode(),
        \strlen($body)
    ));
};

$requests = [];

$requests[] = async($loop, $request, 'GET', 'https://reactphp.org');
$requests[] = async($loop, $request, 'GET', 'https://google.com');
$requests[] = async($loop, $request, 'GET', 'https://www.php.net');

await(Promise\all($requests), $loop);
