<?php

namespace VISU\Tests\Benchmark;

use VISU\AI\BTContext;
use VISU\AI\StateInterface;
use VISU\AI\StateMachine;
use VISU\AI\StateTransition;
use VISU\ECS\EntityRegistry;

class StateMachineBench
{
    private StateMachine $fsm;
    private BTContext $contextNoTransition;
    private BTContext $contextWithTransition;

    public function setUp(): void
    {
        $entities = new \VISU\ECS\EntityRegistry();

        $this->fsm = new StateMachine();

        $states = ['idle', 'patrol', 'chase', 'attack', 'flee'];
        foreach ($states as $name) {
            $this->fsm->addState(new class($name) implements StateInterface {
                public function __construct(private string $name) {}
                public function getName(): string { return $this->name; }
                public function onEnter(BTContext $context): void {}
                public function onUpdate(BTContext $context): void {}
                public function onExit(BTContext $context): void {}
            });
        }

        // transitions that won't fire
        $this->fsm->addTransition(new StateTransition('idle', 'patrol', fn(BTContext $ctx) => $ctx->get('should_patrol') === true));
        $this->fsm->addTransition(new StateTransition('patrol', 'chase', fn(BTContext $ctx) => $ctx->get('enemy_near') === true));
        $this->fsm->addTransition(new StateTransition('chase', 'attack', fn(BTContext $ctx) => $ctx->get('in_range') === true));
        $this->fsm->addTransition(new StateTransition('attack', 'flee', fn(BTContext $ctx) => $ctx->get('health', 100) < 20));
        $this->fsm->addTransition(new StateTransition('flee', 'idle', fn(BTContext $ctx) => $ctx->get('safe') === true));
        $this->fsm->setInitialState('idle');

        $this->contextNoTransition = new BTContext(1, $entities, 0.016);
        $this->contextWithTransition = new BTContext(1, $entities, 0.016);
        $this->contextWithTransition->set('should_patrol', true);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(10000)
     * @Iterations(5)
     */
    public function benchUpdateNoTransition(): void
    {
        $this->fsm->update($this->contextNoTransition);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(10000)
     * @Iterations(5)
     */
    public function benchUpdateWithTransition(): void
    {
        $this->fsm->update($this->contextWithTransition);
        // reset back to idle for next rev
        $this->fsm->setInitialState('idle');
    }
}
