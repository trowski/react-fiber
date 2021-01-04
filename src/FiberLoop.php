<?php

namespace Trowski\ReactFiber;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

/**
 * This class adds async() and await() methods to LoopInterface, as well as adding a getter
 * for the {@see \FiberScheduler} instance associated with the loop.
 */
final class FiberLoop implements LoopInterface
{
    private LoopInterface $loop;

    private \FiberScheduler $scheduler;

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
     * @psalm-return TValue|array<TValue>
     *
     * @throws \Throwable
     */
    public function await(PromiseInterface $promise): mixed
    {
        $fiber = \Fiber::this();
        $method = $promise instanceof ExtendedPromiseInterface ? 'done' : 'then';

        $promise->{$method}(
            fn($value) => $this->loop->futureTick(static fn() => $fiber->resume($value)),
            fn($reason) => $this->loop->futureTick(static fn() => $fiber->throw(
                $reason instanceof \Throwable ? $reason : new RejectedException($reason)
            ))
        );

        return \Fiber::suspend($this->getScheduler());
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

            $this->loop->futureTick(static fn() => $fiber->start());
        });
    }

    /**
     * @return \FiberScheduler The fiber scheduler associated with the wrapped LoopInterface instance.
     */
    public function getScheduler(): \FiberScheduler
    {
        if (!isset($this->scheduler) || $this->scheduler->isTerminated()) {
            $this->scheduler = new \FiberScheduler(fn() => $this->loop->run());
        }

        return $this->scheduler;
    }
}