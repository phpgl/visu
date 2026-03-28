<?php

namespace VISU\AI\BT;

use VISU\AI\BTContext;
use VISU\AI\BTNode;
use VISU\AI\BTStatus;

/**
 * Leaf node that executes a user-defined callback.
 */
class ActionNode extends BTNode
{
    /**
     * @param \Closure(BTContext): BTStatus $action
     */
    public function __construct(
        private \Closure $action,
    ) {
    }

    public function tick(BTContext $context): BTStatus
    {
        return ($this->action)($context);
    }
}
