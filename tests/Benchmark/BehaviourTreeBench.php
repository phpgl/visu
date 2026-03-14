<?php

namespace VISU\Tests\Benchmark;

use VISU\AI\BT\ActionNode;
use VISU\AI\BT\ConditionNode;
use VISU\AI\BT\SelectorNode;
use VISU\AI\BT\SequenceNode;
use VISU\AI\BTContext;
use VISU\AI\BTStatus;
use VISU\ECS\EntityRegistry;

class BehaviourTreeBench
{
    private SequenceNode $shallowTree;
    private SelectorNode $deepTree;
    private BTContext $context;

    public function setUp(): void
    {
        $entities = new \VISU\ECS\EntityRegistry();

        $this->context = new BTContext(1, $entities, 0.016);
        $this->context->set('health', 80);
        $this->context->set('enemy_near', true);
        $this->context->set('ammo', 10);

        // shallow tree: 10 actions in sequence
        $actions = [];
        for ($i = 0; $i < 10; $i++) {
            $actions[] = new ActionNode(fn() => BTStatus::Success);
        }
        $this->shallowTree = new SequenceNode($actions);

        // deep tree: nested selectors with conditions
        $this->deepTree = new SelectorNode([
            new SequenceNode([
                new ConditionNode(fn(BTContext $ctx) => $ctx->get('health') < 20),
                new ActionNode(fn() => BTStatus::Success), // flee
            ]),
            new SequenceNode([
                new ConditionNode(fn(BTContext $ctx) => $ctx->get('enemy_near') === true),
                new ConditionNode(fn(BTContext $ctx) => $ctx->get('ammo') > 0),
                new ActionNode(fn(BTContext $ctx) => BTStatus::Success), // attack
            ]),
            new SequenceNode([
                new ConditionNode(fn(BTContext $ctx) => $ctx->get('enemy_near') === true),
                new ActionNode(fn() => BTStatus::Success), // melee
            ]),
            new ActionNode(fn() => BTStatus::Success), // patrol
        ]);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(10000)
     * @Iterations(5)
     */
    public function benchShallowTree10Nodes(): void
    {
        $this->shallowTree->tick($this->context);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(10000)
     * @Iterations(5)
     */
    public function benchDeepTreeWithConditions(): void
    {
        $this->deepTree->tick($this->context);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(10000)
     * @Iterations(5)
     */
    public function benchBlackboardReadWrite(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->context->set('key_' . $i, $i * 1.5);
            $this->context->get('key_' . $i);
        }
    }
}
