<?php

namespace Tests\Component;

use PHPUnit\Framework\TestCase;
use VISU\AI\StateMachine;
use VISU\Component\StateMachineComponent;

class StateMachineComponentTest extends TestCase
{
    public function testDefaults(): void
    {
        $comp = new StateMachineComponent();

        $this->assertNull($comp->stateMachine);
        $this->assertEmpty($comp->blackboard);
        $this->assertTrue($comp->enabled);
    }

    public function testWithStateMachine(): void
    {
        $sm = new StateMachine();
        $comp = new StateMachineComponent($sm);

        $this->assertSame($sm, $comp->stateMachine);
    }
}
