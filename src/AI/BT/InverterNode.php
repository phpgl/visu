<?php

namespace VISU\AI\BT;

use VISU\AI\BTContext;
use VISU\AI\BTNode;
use VISU\AI\BTStatus;

/**
 * Inverts the result of its child (Success <-> Failure, Running stays Running).
 */
class InverterNode extends BTNode
{
    public function __construct(
        private BTNode $child,
    ) {
    }

    public function tick(BTContext $context): BTStatus
    {
        $status = $this->child->tick($context);

        return match ($status) {
            BTStatus::Success => BTStatus::Failure,
            BTStatus::Failure => BTStatus::Success,
            BTStatus::Running => BTStatus::Running,
        };
    }

    public function reset(): void
    {
        $this->child->reset();
    }
}
