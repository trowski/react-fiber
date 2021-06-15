<?php

namespace Trowski\ReactFiber;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

/**
 * This class adds async() and await() methods to LoopInterface.
 */
final class FiberLoop implements LoopInterface
{
    private LoopInterface $loop;

    private \Fiber $fiber;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function addReadStream($stream, $listener): void
    {
        $this->loop->addReadStream($stream, $listener);
    }

    public function addWriteStream($stream, $listener): void
    {
        $this->loop->addWriteStream($stream, $listener);
    }

    public function removeReadStream($stream): void
    {
        $this->loop->removeReadStream($stream);
    }

    public function removeWriteStream($stream): void
    {
        $this->loop->removeWriteStream($stream);
    }

    public function addTimer($interval, $callback): TimerInterface
    {
        return $this->loop->addTimer($interval, $callback);
    }

    public function addPeriodicTimer($interval, $callback): TimerInterface
    {
        return $this->loop->addPeriodicTimer($interval, $callback);
    }

    public function cancelTimer(TimerInterface $timer): void
    {
        $this->loop->cancelTimer($timer);
    }

    public function futureTick($listener): void
    {
        $this->loop->futureTick($listener);
    }

    public function addSignal($signal, $listener): void
    {
        $this->loop->addSignal($signal, $listener);
    }

    public function removeSignal($signal, $listener): void
    {
        $this->loop->removeSignal($signal, $listener);
    }

    public function run(): void
    {
        $this->loop->run();
    }

    public function stop(): void
    {
        $this->loop->stop();
    }

    /**
     * @template TValue
     *
     * @param PromiseInterface $promise
     *
     * @psalm-param PromiseInterface<TValue> $promise
     *
     * @return mixed
     *
     * @psalm-return TValue
     *
     * @throws \Throwable
     */
    public function await(PromiseInterface $promise): mixed
    {
        $fiber = \Fiber::this();
        $method = $promise instanceof ExtendedPromiseInterface ? 'done' : 'then';

        $resolved = false;

        if ($fiber === null) {
            // Awaiting from {main}.
            if (!isset($this->fiber) || $this->fiber->isTerminated()) {
                $this->fiber = $loop = new \Fiber(fn() => $this->run());
                // Run event loop to completion on shutdown.
                \register_shutdown_function(static function () use ($loop): void {
                    if ($loop->isSuspended()) {
                        $loop->resume();
                    }
                });
            }

            $promise->{$method}(
                function (mixed $value) use (&$resolved): void {
                    $resolved = true;
                    $this->futureTick(static fn() => \Fiber::suspend(static fn() => $value));
                },
                function (mixed $reason) use (&$resolved): void {
                    $resolved = true;
                    $exception = $reason instanceof \Throwable ? $reason : new RejectedException($reason);
                    $this->futureTick(static fn() => \Fiber::suspend(static fn() => throw $exception));
                }
            );

            $lambda = $this->fiber->isStarted() ? $this->fiber->resume() : $this->fiber->start();

            if (!$resolved) {
                throw new \Error('Event loop suspended or exited without resolving the promise');
            }

            return $lambda();
        }

        if (isset($this->fiber) && $fiber === $this->fiber) {
            throw new \Error(\sprintf("Cannot call %s::%s() from a loop event handler callback", self::class, __METHOD__));
        }

        $promise->{$method}(
            function (mixed $value) use (&$resolved, $fiber): void {
                $resolved = true;
                $this->futureTick(static fn() => $fiber->resume($value));
            },
            function (mixed $reason) use (&$resolved, $fiber): void {
                $resolved = true;
                $exception = $reason instanceof \Throwable ? $reason : new RejectedException($reason);
                $this->futureTick(static fn() => $fiber->throw($exception));
            }
        );

        try {
            $result = \Fiber::suspend();
        } finally {
            if (!$resolved) {
                throw new \Error('Fiber resumed before the promise was resolved');
            }
        }

        return $result;
    }


    /**
     * Create a new fiber (green-thread) using the given callback. The returned promise is
     * resolved with the return value of the callback once the fiber completes execution.
     *
     * @template TReturn
     *
     * @param callable $callback
     * @param mixed ...$args
     *
     * @psalm-param callable(mixed ...$args):TReturn $callback
     *
     * @return ExtendedPromiseInterface
     *
     * @psalm-return ExtendedPromiseInterface<TReturn>
     */
    public function async(callable $callback, mixed ...$args): ExtendedPromiseInterface
    {
        return new Promise(function (callable $resolve, callable $reject) use ($callback, $args): void {
            $fiber = new \Fiber(function () use ($resolve, $reject, $callback, $args): void {
                try {
                    $resolve($callback(...$args));
                } catch (\Throwable $exception) {
                    $reject($exception);
                }
            });

            $this->futureTick(static fn() => $fiber->start());
        });
    }
}
