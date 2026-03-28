<?php

namespace Tests\Component;

use PHPUnit\Framework\TestCase;
use VISU\AI\BT\ActionNode;
use VISU\AI\BTStatus;
use VISU\Component\BehaviourTreeComponent;

class BehaviourTreeComponentTest extends TestCase
{
    public function testDefaults(): void
    {
        $comp = new BehaviourTreeComponent();

        $this->assertNull($comp->root);
        $this->assertEmpty($comp->blackboard);
        $this->assertSame(BTStatus::Success, $comp->lastStatus);
        $this->assertTrue($comp->enabled);
    }

    public function testWithRoot(): void
    {
        $node = new ActionNode(fn() => BTStatus::Success);
        $comp = new BehaviourTreeComponent($node);

        $this->assertSame($node, $comp->root);
    }
}
