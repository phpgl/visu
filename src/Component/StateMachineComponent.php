<?php

namespace VISU\Component;

use VISU\AI\StateMachine;

class StateMachineComponent
{
    /**
     * @var array<string, mixed> Blackboard data persisted across ticks
     */
    public array $blackboard = [];

    public bool $enabled = true;

    public function __construct(
        public ?StateMachine $stateMachine = null,
    ) {
    }
}
