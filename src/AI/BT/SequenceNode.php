<?php

namespace VISU\AI\BT;

use VISU\AI\BTContext;
use VISU\AI\BTNode;
use VISU\AI\BTStatus;

/**
 * Executes children in order. Fails on first failure, succeeds when all succeed.
 */
class SequenceNode extends BTNode
{
    private int $currentIndex = 0;

    /**
     * @param array<BTNode> $children
     */
    public function __construct(
        private array $children,
    ) {
    }

    public function tick(BTContext $context): BTStatus
    {
        while ($this->currentIndex < count($this->children)) {
            $status = $this->children[$this->currentIndex]->tick($context);

            if ($status === BTStatus::Running) {
                return BTStatus::Running;
            }

            if ($status === BTStatus::Failure) {
                $this->currentIndex = 0;
                return BTStatus::Failure;
            }

            $this->currentIndex++;
        }

        $this->currentIndex = 0;
        return BTStatus::Success;
    }

    public function reset(): void
    {
        $this->currentIndex = 0;
        foreach ($this->children as $child) {
            $child->reset();
        }
    }
}
