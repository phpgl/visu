<?php

namespace VISU\AI;

class StateTransition
{
    /**
     * @param string $fromState Source state name
     * @param string $toState Target state name
     * @param \Closure(BTContext): bool $condition Evaluated each tick
     */
    public function __construct(
        public readonly string $fromState,
        public readonly string $toState,
        private \Closure $condition,
    ) {
    }

    public function evaluate(BTContext $context): bool
    {
        return ($this->condition)($context);
    }
}
