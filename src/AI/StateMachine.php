<?php

namespace VISU\AI;

class StateMachine
{
    /**
     * @var array<string, StateInterface>
     */
    private array $states = [];

    /**
     * @var array<StateTransition>
     */
    private array $transitions = [];

    private ?string $currentStateName = null;

    public function addState(StateInterface $state): void
    {
        $this->states[$state->getName()] = $state;
    }

    public function addTransition(StateTransition $transition): void
    {
        $this->transitions[] = $transition;
    }

    public function setInitialState(string $name): void
    {
        if (!isset($this->states[$name])) {
            throw new \InvalidArgumentException("State '{$name}' not registered.");
        }
        $this->currentStateName = $name;
    }

    public function getCurrentStateName(): ?string
    {
        return $this->currentStateName;
    }

    public function getCurrentState(): ?StateInterface
    {
        if ($this->currentStateName === null) {
            return null;
        }
        return $this->states[$this->currentStateName] ?? null;
    }

    public function update(BTContext $context): void
    {
        if ($this->currentStateName === null) {
            return;
        }

        // check transitions
        foreach ($this->transitions as $transition) {
            if ($transition->fromState !== $this->currentStateName) {
                continue;
            }

            if (!$transition->evaluate($context)) {
                continue;
            }

            if (!isset($this->states[$transition->toState])) {
                continue;
            }

            // transition
            $this->states[$this->currentStateName]->onExit($context);
            $this->currentStateName = $transition->toState;
            $this->states[$this->currentStateName]->onEnter($context);
            return;
        }

        // update current state
        $this->states[$this->currentStateName]->onUpdate($context);
    }

    /**
     * Force transition to a state (bypassing conditions)
     */
    public function forceTransition(string $name, BTContext $context): void
    {
        if (!isset($this->states[$name])) {
            throw new \InvalidArgumentException("State '{$name}' not registered.");
        }

        if ($this->currentStateName !== null && isset($this->states[$this->currentStateName])) {
            $this->states[$this->currentStateName]->onExit($context);
        }

        $this->currentStateName = $name;
        $this->states[$name]->onEnter($context);
    }
}
