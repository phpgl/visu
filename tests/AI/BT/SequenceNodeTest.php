<?php

namespace Tests\AI\BT;

use PHPUnit\Framework\TestCase;
use VISU\AI\BT\ActionNode;
use VISU\AI\BT\SequenceNode;
use VISU\AI\BTContext;
use VISU\AI\BTStatus;

class SequenceNodeTest extends TestCase
{
    private function makeContext(): BTContext
    {
        $entities = $this->createMock(\VISU\ECS\EntitiesInterface::class);
        return new BTContext(1, $entities, 0.016);
    }

    public function testAllSucceed(): void
    {
        $seq = new SequenceNode([
            new ActionNode(fn() => BTStatus::Success),
            new ActionNode(fn() => BTStatus::Success),
            new ActionNode(fn() => BTStatus::Success),
        ]);

        $this->assertSame(BTStatus::Success, $seq->tick($this->makeContext()));
    }

    public function testFailsOnFirstFailure(): void
    {
        $called = false;
        $seq = new SequenceNode([
            new ActionNode(fn() => BTStatus::Success),
            new ActionNode(fn() => BTStatus::Failure),
            new ActionNode(function () use (&$called) {
                $called = true;
                return BTStatus::Success;
            }),
        ]);

        $this->assertSame(BTStatus::Failure, $seq->tick($this->makeContext()));
        $this->assertFalse($called);
    }

    public function testRunningPausesExecution(): void
    {
        $count = 0;
        $seq = new SequenceNode([
            new ActionNode(function () use (&$count) {
                $count++;
                return BTStatus::Success;
            }),
            new ActionNode(fn() => BTStatus::Running),
            new ActionNode(fn() => BTStatus::Success),
        ]);

        $this->assertSame(BTStatus::Running, $seq->tick($this->makeContext()));

        // second tick resumes from running child
        $this->assertSame(BTStatus::Running, $seq->tick($this->makeContext()));

        // first child was only called once (on first tick), sequence resumes at index 1
        $this->assertEquals(1, $count);
    }

    public function testResetClearsState(): void
    {
        $count = 0;
        $action = new ActionNode(function () use (&$count) {
            $count++;
            return BTStatus::Running;
        });

        $seq = new SequenceNode([
            new ActionNode(fn() => BTStatus::Success),
            $action,
        ]);

        $seq->tick($this->makeContext());
        $seq->reset();
        $seq->tick($this->makeContext());

        // after reset, sequence starts from beginning again
        $this->assertEquals(2, $count);
    }
}
