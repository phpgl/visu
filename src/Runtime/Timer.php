<?php

namespace VISU\Runtime;

use Closure;

class Timer
{
    private bool $cancelled = false;

    public function __construct(
        public readonly int $time,
        public readonly Closure $callback,
        public readonly int $id
    ) {}

    /**
     * Mark this timer as cancelled
     */
    public function cancel(): void
    {
        $this->cancelled = true;
    }

    /**
     * Check if this timer has been cancelled
     */
    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
}