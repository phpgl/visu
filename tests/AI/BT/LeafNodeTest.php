<?php

namespace Tests\AI\BT;

use PHPUnit\Framework\TestCase;
use VISU\AI\BT\ActionNode;
use VISU\AI\BT\ConditionNode;
use VISU\AI\BTContext;
use VISU\AI\BTStatus;

class LeafNodeTest extends TestCase
{
    private function makeContext(): BTContext
    {
        $entities = $this->createMock(\VISU\ECS\EntitiesInterface::class);
        return new BTContext(1, $entities, 0.016);
    }

    public function testActionNodeReturnsCallbackResult(): void
    {
        $action = new ActionNode(fn() => BTStatus::Success);
        $this->assertSame(BTStatus::Success, $action->tick($this->makeContext()));
    }

    public function testActionNodeReceivesContext(): void
    {
        $action = new ActionNode(function (BTContext $ctx) {
            $ctx->set('visited', true);
            return BTStatus::Success;
        });

        $ctx = $this->makeContext();
        $action->tick($ctx);
        $this->assertTrue($ctx->get('visited'));
    }

    public function testConditionTrue(): void
    {
        $cond = new ConditionNode(fn() => true);
        $this->assertSame(BTStatus::Success, $cond->tick($this->makeContext()));
    }

    public function testConditionFalse(): void
    {
        $cond = new ConditionNode(fn() => false);
        $this->assertSame(BTStatus::Failure, $cond->tick($this->makeContext()));
    }

    public function testConditionChecksBlackboard(): void
    {
        $cond = new ConditionNode(fn(BTContext $ctx) => $ctx->get('health', 0) > 50);

        $ctx = $this->makeContext();
        $ctx->set('health', 100);
        $this->assertSame(BTStatus::Success, $cond->tick($ctx));

        $ctx2 = $this->makeContext();
        $ctx2->set('health', 10);
        $this->assertSame(BTStatus::Failure, $cond->tick($ctx2));
    }
}
