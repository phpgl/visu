<?php

namespace VISU\System;

use VISU\AI\BTContext;
use VISU\Component\BehaviourTreeComponent;
use VISU\Component\StateMachineComponent;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Graphics\Rendering\RenderContext;

class AISystem implements SystemInterface
{
    private float $deltaTime = 0.0;

    public function register(EntitiesInterface $entities): void
    {
        $entities->registerComponent(BehaviourTreeComponent::class);
        $entities->registerComponent(StateMachineComponent::class);
    }

    public function unregister(EntitiesInterface $entities): void
    {
    }

    public function setDeltaTime(float $dt): void
    {
        $this->deltaTime = $dt;
    }

    public function update(EntitiesInterface $entities): void
    {
        // tick behaviour trees
        foreach ($entities->view(BehaviourTreeComponent::class) as $entity => $bt) {
            if (!$bt->enabled || $bt->root === null) {
                continue;
            }

            $context = new BTContext($entity, $entities, $this->deltaTime);
            $context->blackboard = $bt->blackboard;

            $bt->lastStatus = $bt->root->tick($context);
            $bt->blackboard = $context->blackboard;
        }

        // tick state machines
        foreach ($entities->view(StateMachineComponent::class) as $entity => $sm) {
            if (!$sm->enabled || $sm->stateMachine === null) {
                continue;
            }

            $context = new BTContext($entity, $entities, $this->deltaTime);
            $context->blackboard = $sm->blackboard;

            $sm->stateMachine->update($context);
            $sm->blackboard = $context->blackboard;
        }
    }

    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
    }
}
