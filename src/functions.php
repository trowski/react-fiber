<?php

namespace Trowski\ReactFiber;

use React\Promise\ExtendedPromiseInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use function React\Promise\all;

function await(PromiseInterface|array $promise, FiberLoop $loop): mixed
{
    if (!$promise instanceof PromiseInterface) {
        $promise = all($promise);
    }

    $fiber = \Fiber::this();
    $method = $promise instanceof ExtendedPromiseInterface ? 'done' : 'then';

    $promise->{$method}(
        static fn($value) => $loop->futureTick(static fn() => $fiber->resume($value)),
        static fn($reason) => $loop->futureTick(static fn() => $fiber->throw(
            $reason instanceof \Throwable ? $reason : new RejectedException($reason)
        ))
    );

    return \Fiber::suspend($loop);
}

function async(FiberLoop $loop, callable $callback, mixed ...$args): ExtendedPromiseInterface
{
    return new Promise(function (callable $resolve, callable $reject) use ($loop, $callback, $args): void {
        $fiber = \Fiber::create(function () use ($resolve, $reject, $loop, $callback, $args): void {
            try {
                $resolve($callback(...$args));
            } catch (\Throwable $exception) {
                $reject($exception);
            }
        });

        $loop->futureTick(fn() => $fiber->start());
    });
}
