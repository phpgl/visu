<?php

namespace VISU\AI\BT;

use VISU\AI\BTContext;
use VISU\AI\BTNode;
use VISU\AI\BTStatus;

/**
 * Always returns Success regardless of child result (Running stays Running).
 */
class SucceederNode extends BTNode
{
    public function __construct(
        private BTNode $child,
    ) {
    }

    public function tick(BTContext $context): BTStatus
    {
        $status = $this->child->tick($context);

        if ($status === BTStatus::Running) {
            return BTStatus::Running;
        }

        return BTStatus::Success;
    }

    public function reset(): void
    {
        $this->child->reset();
    }
}
