<?php

namespace Tests\AI\BT;

use PHPUnit\Framework\TestCase;
use VISU\AI\BT\ActionNode;
use VISU\AI\BT\InverterNode;
use VISU\AI\BT\RepeaterNode;
use VISU\AI\BT\SucceederNode;
use VISU\AI\BTContext;
use VISU\AI\BTStatus;

class DecoratorNodeTest extends TestCase
{
    private function makeContext(): BTContext
    {
        $entities = $this->createMock(\VISU\ECS\EntitiesInterface::class);
        return new BTContext(1, $entities, 0.016);
    }

    public function testInverterSuccess(): void
    {
        $inv = new InverterNode(new ActionNode(fn() => BTStatus::Success));
        $this->assertSame(BTStatus::Failure, $inv->tick($this->makeContext()));
    }

    public function testInverterFailure(): void
    {
        $inv = new InverterNode(new ActionNode(fn() => BTStatus::Failure));
        $this->assertSame(BTStatus::Success, $inv->tick($this->makeContext()));
    }

    public function testInverterRunning(): void
    {
        $inv = new InverterNode(new ActionNode(fn() => BTStatus::Running));
        $this->assertSame(BTStatus::Running, $inv->tick($this->makeContext()));
    }

    public function testRepeaterFiniteCount(): void
    {
        $count = 0;
        $rep = new RepeaterNode(
            new ActionNode(function () use (&$count) {
                $count++;
                return BTStatus::Success;
            }),
            maxRepetitions: 3,
        );

        $ctx = $this->makeContext();
        $this->assertSame(BTStatus::Running, $rep->tick($ctx)); // 1st
        $this->assertSame(BTStatus::Running, $rep->tick($ctx)); // 2nd
        $this->assertSame(BTStatus::Success, $rep->tick($ctx)); // 3rd, done
        $this->assertEquals(3, $count);
    }

    public function testRepeaterStopsOnFailure(): void
    {
        $rep = new RepeaterNode(
            new ActionNode(fn() => BTStatus::Failure),
            maxRepetitions: 5,
        );

        $this->assertSame(BTStatus::Failure, $rep->tick($this->makeContext()));
    }

    public function testSucceederConvertsFailure(): void
    {
        $succ = new SucceederNode(new ActionNode(fn() => BTStatus::Failure));
        $this->assertSame(BTStatus::Success, $succ->tick($this->makeContext()));
    }

    public function testSucceederPassesRunning(): void
    {
        $succ = new SucceederNode(new ActionNode(fn() => BTStatus::Running));
        $this->assertSame(BTStatus::Running, $succ->tick($this->makeContext()));
    }
}
