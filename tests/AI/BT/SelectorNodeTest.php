<?php

namespace Tests\AI\BT;

use PHPUnit\Framework\TestCase;
use VISU\AI\BT\ActionNode;
use VISU\AI\BT\SelectorNode;
use VISU\AI\BTContext;
use VISU\AI\BTStatus;

class SelectorNodeTest extends TestCase
{
    private function makeContext(): BTContext
    {
        $entities = $this->createMock(\VISU\ECS\EntitiesInterface::class);
        return new BTContext(1, $entities, 0.016);
    }

    public function testSucceedsOnFirstSuccess(): void
    {
        $called = false;
        $sel = new SelectorNode([
            new ActionNode(fn() => BTStatus::Failure),
            new ActionNode(fn() => BTStatus::Success),
            new ActionNode(function () use (&$called) {
                $called = true;
                return BTStatus::Success;
            }),
        ]);

        $this->assertSame(BTStatus::Success, $sel->tick($this->makeContext()));
        $this->assertFalse($called);
    }

    public function testAllFail(): void
    {
        $sel = new SelectorNode([
            new ActionNode(fn() => BTStatus::Failure),
            new ActionNode(fn() => BTStatus::Failure),
        ]);

        $this->assertSame(BTStatus::Failure, $sel->tick($this->makeContext()));
    }

    public function testRunning(): void
    {
        $sel = new SelectorNode([
            new ActionNode(fn() => BTStatus::Failure),
            new ActionNode(fn() => BTStatus::Running),
        ]);

        $this->assertSame(BTStatus::Running, $sel->tick($this->makeContext()));
    }
}
