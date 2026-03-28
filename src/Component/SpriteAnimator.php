<?php

namespace VISU\Component;

class SpriteAnimator
{
    /**
     * Current animation name.
     */
    public string $currentAnimation = '';

    /**
     * Animation definitions: name => config.
     * Each config: ['frames' => [[u, v, w, h], ...], 'fps' => int, 'loop' => bool]
     *
     * @var array<string, array{frames: array<array{float, float, float, float}>, fps: int, loop: bool}>
     */
    public array $animations = [];

    /**
     * Current frame index in the active animation.
     */
    public int $currentFrame = 0;

    /**
     * Accumulated time since last frame change (seconds).
     */
    public float $elapsed = 0.0;

    /**
     * Whether the current animation is playing.
     */
    public bool $playing = true;

    /**
     * Whether the animation has finished (only relevant for non-looping).
     */
    public bool $finished = false;

    /**
     * Plays an animation by name, resetting frame state.
     */
    public function play(string $name): void
    {
        if ($this->currentAnimation === $name && $this->playing && !$this->finished) {
            return;
        }

        $this->currentAnimation = $name;
        $this->currentFrame = 0;
        $this->elapsed = 0.0;
        $this->playing = true;
        $this->finished = false;
    }
}
