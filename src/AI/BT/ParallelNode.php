<?php

namespace VISU\AI\BT;

use VISU\AI\BTContext;
use VISU\AI\BTNode;
use VISU\AI\BTStatus;

/**
 * Ticks all children every frame.
 * Succeeds when requiredSuccesses children succeed.
 * Fails when enough children fail that success is impossible.
 */
class ParallelNode extends BTNode
{
    /**
     * @param array<BTNode> $children
     * @param int $requiredSuccesses Number of children that must succeed (0 = all)
     */
    public function __construct(
        private array $children,
        private int $requiredSuccesses = 0,
    ) {
        if ($this->requiredSuccesses <= 0) {
            $this->requiredSuccesses = count($this->children);
        }
    }

    public function tick(BTContext $context): BTStatus
    {
        $successCount = 0;
        $failureCount = 0;
        $total = count($this->children);

        foreach ($this->children as $child) {
            $status = $child->tick($context);

            if ($status === BTStatus::Success) {
                $successCount++;
            } elseif ($status === BTStatus::Failure) {
                $failureCount++;
            }
        }

        if ($successCount >= $this->requiredSuccesses) {
            return BTStatus::Success;
        }

        $maxPossibleSuccesses = $total - $failureCount;
        if ($maxPossibleSuccesses < $this->requiredSuccesses) {
            return BTStatus::Failure;
        }

        return BTStatus::Running;
    }

    public function reset(): void
    {
        foreach ($this->children as $child) {
            $child->reset();
        }
    }
}
