<?php

namespace Tests\AI;

use PHPUnit\Framework\TestCase;
use VISU\AI\BTContext;
use VISU\AI\StateInterface;
use VISU\AI\StateMachine;
use VISU\AI\StateTransition;

class StateMachineTest extends TestCase
{
    private function makeContext(): BTContext
    {
        $entities = $this->createMock(\VISU\ECS\EntitiesInterface::class);
        return new BTContext(1, $entities, 0.016);
    }

    private function createState(string $name): StateInterface
    {
        return new class($name) implements StateInterface {
            /** @var array<string> */
            public array $log = [];

            public function __construct(private string $name)
            {
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function onEnter(BTContext $context): void
            {
                $this->log[] = 'enter';
            }

            public function onUpdate(BTContext $context): void
            {
                $this->log[] = 'update';
            }

            public function onExit(BTContext $context): void
            {
                $this->log[] = 'exit';
            }
        };
    }

    public function testInitialState(): void
    {
        $sm = new StateMachine();
        $idle = $this->createState('idle');
        $sm->addState($idle);
        $sm->setInitialState('idle');

        $this->assertSame('idle', $sm->getCurrentStateName());
    }

    public function testUpdateCallsCurrentState(): void
    {
        $sm = new StateMachine();
        $idle = $this->createState('idle');
        $sm->addState($idle);
        $sm->setInitialState('idle');

        $sm->update($this->makeContext());
        /** @phpstan-ignore-next-line */
        $this->assertEquals(['update'], $idle->log);
    }

    public function testTransition(): void
    {
        $sm = new StateMachine();
        $idle = $this->createState('idle');
        $chase = $this->createState('chase');
        $sm->addState($idle);
        $sm->addState($chase);

        $sm->addTransition(new StateTransition(
            'idle',
            'chase',
            fn(BTContext $ctx) => $ctx->get('enemy_near', false) === true,
        ));

        $sm->setInitialState('idle');

        // no transition yet
        $ctx = $this->makeContext();
        $sm->update($ctx);
        $this->assertSame('idle', $sm->getCurrentStateName());

        // trigger transition
        $ctx2 = $this->makeContext();
        $ctx2->set('enemy_near', true);
        $sm->update($ctx2);
        $this->assertSame('chase', $sm->getCurrentStateName());

        /** @phpstan-ignore-next-line */
        $this->assertContains('exit', $idle->log);
        /** @phpstan-ignore-next-line */
        $this->assertContains('enter', $chase->log);
    }

    public function testForceTransition(): void
    {
        $sm = new StateMachine();
        $idle = $this->createState('idle');
        $dead = $this->createState('dead');
        $sm->addState($idle);
        $sm->addState($dead);
        $sm->setInitialState('idle');

        $sm->forceTransition('dead', $this->makeContext());
        $this->assertSame('dead', $sm->getCurrentStateName());

        /** @phpstan-ignore-next-line */
        $this->assertContains('exit', $idle->log);
        /** @phpstan-ignore-next-line */
        $this->assertContains('enter', $dead->log);
    }

    public function testInvalidStateThrows(): void
    {
        $sm = new StateMachine();
        $this->expectException(\InvalidArgumentException::class);
        $sm->setInitialState('nonexistent');
    }

    public function testForceTransitionInvalidThrows(): void
    {
        $sm = new StateMachine();
        $this->expectException(\InvalidArgumentException::class);
        $sm->forceTransition('nonexistent', $this->makeContext());
    }

    public function testNullStateDoesNothing(): void
    {
        $sm = new StateMachine();
        $sm->update($this->makeContext()); // no crash
        $this->assertNull($sm->getCurrentStateName());
    }
}
