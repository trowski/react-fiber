<?php

namespace Trowski\ReactFiber;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

/**
 * This class exists to attach the FiberScheduler interface to LoopInterface.
 */
final class FiberLoop implements LoopInterface, \FiberScheduler
{
    private LoopInterface $loop;

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
}