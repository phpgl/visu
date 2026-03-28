<?php

namespace VISU\AI\BT;

use VISU\AI\BTContext;
use VISU\AI\BTNode;
use VISU\AI\BTStatus;

/**
 * Repeats its child a given number of times (0 = infinite).
 * Returns Running while repeating, Success when count reached.
 * Stops and returns Failure if child fails.
 */
class RepeaterNode extends BTNode
{
    private int $iteration = 0;

    public function __construct(
        private BTNode $child,
        private int $maxRepetitions = 0,
    ) {
    }

    public function tick(BTContext $context): BTStatus
    {
        $status = $this->child->tick($context);

        if ($status === BTStatus::Running) {
            return BTStatus::Running;
        }

        if ($status === BTStatus::Failure) {
            $this->iteration = 0;
            return BTStatus::Failure;
        }

        // child succeeded
        $this->iteration++;
        $this->child->reset();

        if ($this->maxRepetitions > 0 && $this->iteration >= $this->maxRepetitions) {
            $this->iteration = 0;
            return BTStatus::Success;
        }

        return BTStatus::Running;
    }

    public function reset(): void
    {
        $this->iteration = 0;
        $this->child->reset();
    }
}
