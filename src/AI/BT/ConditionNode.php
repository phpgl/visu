<?php

namespace VISU\AI\BT;

use VISU\AI\BTContext;
use VISU\AI\BTNode;
use VISU\AI\BTStatus;

/**
 * Leaf node that evaluates a boolean condition.
 * Returns Success if true, Failure if false. Never Running.
 */
class ConditionNode extends BTNode
{
    /**
     * @param \Closure(BTContext): bool $condition
     */
    public function __construct(
        private \Closure $condition,
    ) {
    }

    public function tick(BTContext $context): BTStatus
    {
        return ($this->condition)($context) ? BTStatus::Success : BTStatus::Failure;
    }
}
