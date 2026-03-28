<?php

namespace VISU\Component;

use VISU\AI\BTNode;
use VISU\AI\BTStatus;

class BehaviourTreeComponent
{
    /**
     * @var array<string, mixed> Blackboard data persisted across ticks
     */
    public array $blackboard = [];

    public BTStatus $lastStatus = BTStatus::Success;

    public bool $enabled = true;

    public function __construct(
        public ?BTNode $root = null,
    ) {
    }
}
