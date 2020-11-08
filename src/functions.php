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

    if ($promise instanceof ExtendedPromiseInterface) {
        $enqueue = static fn(\Continuation $continuation) => $promise->done(
            static fn($value) => $loop->futureTick(static fn() => $continuation->resume($value)),
            static fn($reason) => $loop->futureTick(static fn() => $continuation->throw(
                $reason instanceof \Throwable? $reason : new RejectedException($reason)
            ))
        );

        return \Fiber::suspend($enqueue, $loop);
    }

    $enqueue = static fn(\Continuation $continuation) => $promise->then(
        static fn($value) => $loop->futureTick(static fn() => $continuation->resume($value)),
        static fn($reason) => $loop->futureTick(static fn() => $continuation->throw(
            $reason instanceof \Throwable? $reason : new RejectedException($reason)
        ))
    );

    return \Fiber::suspend($enqueue, $loop);
}

function async(FiberLoop $loop, callable $callback, mixed ...$args): ExtendedPromiseInterface
{
    return new Promise(function (callable $resolve, callable $reject) use ($loop, $callback, $args): void {
        $defer = static fn() => \Fiber::run(function () use ($resolve, $reject, $loop, $callback, $args): void {
            try {
                $resolve($callback(...$args));
            } catch (\Throwable $exception) {
                $reject($exception);
            }
        });

        $loop->futureTick($defer);
    });
}
