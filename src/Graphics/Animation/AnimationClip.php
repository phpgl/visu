<?php

namespace VISU\Graphics\Animation;

class AnimationClip
{
    /**
     * @var array<AnimationChannel>
     */
    public array $channels = [];

    public function __construct(
        public readonly string $name,
        public float $duration = 0.0,
    ) {
    }

    public function addChannel(AnimationChannel $channel): void
    {
        $this->channels[] = $channel;
    }
}
