<?php

namespace VISU\UI;

class UIScreen
{
    private ?UITransition $enterTransition = null;
    private ?UITransition $exitTransition = null;
    private bool $active = false;

    /**
     * @param string $name Screen identifier
     * @param string|null $layoutFile Path to a JSON layout file (optional)
     * @param array<string, mixed>|null $layoutData Inline layout data (optional)
     * @param bool $transparent If true, screens below this one are also rendered
     */
    public function __construct(
        public readonly string $name,
        private ?string $layoutFile = null,
        private ?array $layoutData = null,
        private bool $transparent = false,
    ) {
    }

    public function setEnterTransition(UITransitionType $type, float $duration = 0.3, float $delay = 0.0): self
    {
        $this->enterTransition = new UITransition($type, $duration, $delay);
        return $this;
    }

    public function setExitTransition(UITransitionType $type, float $duration = 0.3, float $delay = 0.0): self
    {
        $this->exitTransition = new UITransition($type, $duration, $delay);
        return $this;
    }

    public function getEnterTransition(): ?UITransition
    {
        return $this->enterTransition;
    }

    public function getExitTransition(): ?UITransition
    {
        return $this->exitTransition;
    }

    public function isTransparent(): bool
    {
        return $this->transparent;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLayoutFile(): ?string
    {
        return $this->layoutFile;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLayoutData(): ?array
    {
        return $this->layoutData;
    }

    public function setLayoutFile(string $path): void
    {
        $this->layoutFile = $path;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setLayoutData(array $data): void
    {
        $this->layoutData = $data;
    }

    public function onEnter(): void
    {
        $this->active = true;
        $this->enterTransition?->reset();
    }

    public function onExit(): void
    {
        $this->active = false;
        $this->exitTransition?->reset();
    }

    public function update(float $deltaTime): void
    {
        $this->enterTransition?->update($deltaTime);
        $this->exitTransition?->update($deltaTime);
    }

    public function render(UIInterpreter $interpreter): void
    {
        if ($this->layoutFile !== null) {
            $interpreter->renderFile($this->layoutFile);
        } elseif ($this->layoutData !== null) {
            $interpreter->renderNode($this->layoutData);
        }
    }
}
