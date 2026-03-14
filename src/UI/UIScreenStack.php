<?php

namespace VISU\UI;

class UIScreenStack
{
    /**
     * @var array<UIScreen>
     */
    private array $stack = [];

    /**
     * Pushes a screen onto the stack with an optional transition.
     */
    public function push(UIScreen $screen): void
    {
        $this->stack[] = $screen;
        $screen->onEnter();
    }

    /**
     * Pops the top screen from the stack.
     */
    public function pop(): ?UIScreen
    {
        if (empty($this->stack)) {
            return null;
        }

        $screen = array_pop($this->stack);
        $screen->onExit();
        return $screen;
    }

    /**
     * Replaces the top screen with a new one.
     */
    public function replace(UIScreen $screen): ?UIScreen
    {
        $old = $this->pop();
        $this->push($screen);
        return $old;
    }

    /**
     * Returns the top screen without removing it.
     */
    public function peek(): ?UIScreen
    {
        if (empty($this->stack)) {
            return null;
        }
        return $this->stack[array_key_last($this->stack)];
    }

    /**
     * Returns the number of screens on the stack.
     */
    public function count(): int
    {
        return count($this->stack);
    }

    /**
     * Whether the stack is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->stack);
    }

    /**
     * Clears all screens from the stack.
     */
    public function clear(): void
    {
        while (!empty($this->stack)) {
            $this->pop();
        }
    }

    /**
     * Updates all screens on the stack.
     */
    public function update(float $deltaTime): void
    {
        foreach ($this->stack as $screen) {
            $screen->update($deltaTime);
        }
    }

    /**
     * Renders screens. By default only renders the top screen.
     * If a screen is transparent, it also renders screens below it.
     */
    public function render(UIInterpreter $interpreter): void
    {
        if (empty($this->stack)) {
            return;
        }

        // Find the lowest visible screen (walk from top looking for non-transparent)
        $renderFrom = count($this->stack) - 1;
        for ($i = count($this->stack) - 1; $i > 0; $i--) {
            if (!$this->stack[$i]->isTransparent()) {
                break;
            }
            $renderFrom = $i - 1;
        }

        for ($i = $renderFrom; $i < count($this->stack); $i++) {
            $this->stack[$i]->render($interpreter);
        }
    }

    /**
     * Returns all screens on the stack (bottom to top).
     *
     * @return array<UIScreen>
     */
    public function getScreens(): array
    {
        return $this->stack;
    }
}
