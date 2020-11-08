<?php

namespace Trowski\ReactFiber;

final class RejectedException extends \Exception
{
    private mixed $reason;

    public function __construct(mixed $reason)
    {
        parent::__construct("Promise was rejected");
        $this->reason = $reason;
    }

    public function getReason(): mixed
    {
        return $this->reason;
    }
}
