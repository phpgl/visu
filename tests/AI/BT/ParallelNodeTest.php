<?php

namespace Tests\AI\BT;

use PHPUnit\Framework\TestCase;
use VISU\AI\BT\ActionNode;
use VISU\AI\BT\ParallelNode;
use VISU\AI\BTContext;
use VISU\AI\BTStatus;

class ParallelNodeTest extends TestCase
{
    private function makeContext(): BTContext
    {
        $entities = $this->createMock(\VISU\ECS\EntitiesInterface::class);
        return new BTContext(1, $entities, 0.016);
    }

    public function testAllSucceed(): void
    {
        $par = new ParallelNode([
            new ActionNode(fn() => BTStatus::Success),
            new ActionNode(fn() => BTStatus::Success),
        ]);

        $this->assertSame(BTStatus::Success, $par->tick($this->makeContext()));
    }

    public function testPartialSuccess(): void
    {
        $par = new ParallelNode(
            [
                new ActionNode(fn() => BTStatus::Success),
                new ActionNode(fn() => BTStatus::Running),
                new ActionNode(fn() => BTStatus::Running),
            ],
            requiredSuccesses: 1,
        );

        $this->assertSame(BTStatus::Success, $par->tick($this->makeContext()));
    }

    public function testFailsWhenImpossible(): void
    {
        $par = new ParallelNode(
            [
                new ActionNode(fn() => BTStatus::Failure),
                new ActionNode(fn() => BTStatus::Failure),
                new ActionNode(fn() => BTStatus::Success),
            ],
            requiredSuccesses: 2,
        );

        // only 1 success possible (3 - 2 failures = 1), need 2 => failure
        $this->assertSame(BTStatus::Failure, $par->tick($this->makeContext()));
    }

    public function testRunningWhileInProgress(): void
    {
        $par = new ParallelNode([
            new ActionNode(fn() => BTStatus::Success),
            new ActionNode(fn() => BTStatus::Running),
        ]);

        // need 2 successes, have 1 success + 1 running => still possible
        $this->assertSame(BTStatus::Running, $par->tick($this->makeContext()));
    }
}
