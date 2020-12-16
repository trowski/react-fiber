<?php

namespace Trowski\ReactFiber;

use React\Promise\ExtendedPromiseInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use function React\Promise\all;

/**
 * @template TValue
 *
 * @param PromiseInterface|PromiseInterface[] $promise
 * @param FiberLoop $loop
 *
 * @psalm-param PromiseInterface<TValue>|PromiseInterface<TValue>[] $promise
 *
 * @return mixed
 *
 * @psalm-return TValue|array<TValue>
 *
 * @throws \Throwable
 */
function await(PromiseInterface|array $promise, FiberLoop $loop): mixed
{
    if (!$promise instanceof PromiseInterface) {
        $promise = all($promise);
    }

    $fiber = \Fiber::this();
    $method = $promise instanceof ExtendedPromiseInterface ? 'done' : 'then';

    $promise->{$method}(
        fn($value) => $loop->futureTick(fn() => $fiber->resume($value)),
        fn($reason) => $loop->futureTick(fn() => $fiber->throw(
            $reason instanceof \Throwable ? $reason : new RejectedException($reason)
        ))
    );

    return \Fiber::suspend($loop);
}

/**
 * Create a new fiber (green-thread) using the given callback. The returned promise is
 * resolved with the return value of the callback once the fiber completes execution.
 *
 * @template TReturn
 *
 * @param FiberLoop $loop
 * @param callable $callback
 * @param mixed ...$args
 *
 * @psalm-param callable(mixed ...$args):TReturn $callback
 *
 * @return ExtendedPromiseInterface
 *
 * @psalm-return ExtendedPromiseInterface<TReturn>
 */
function async(FiberLoop $loop, callable $callback, mixed ...$args): ExtendedPromiseInterface
{
    return new Promise(function (callable $resolve, callable $reject) use ($loop, $callback, $args): void {
        $fiber = new \Fiber(function () use ($resolve, $reject, $loop, $callback, $args): void {
            try {
                $resolve($callback(...$args));
            } catch (\Throwable $exception) {
                $reject($exception);
            }
        });

        $loop->futureTick(fn() => $fiber->start());
    });
}
