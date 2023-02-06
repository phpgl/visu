<?php

namespace VISU\Animation\Transition;

class BaseAnimationContainer
{
    public bool $finished = false; // Are all animations finished?

    public bool $running = false; // Is any animation running?

    /**
     * @param BaseAnimation[] $animations Animations to run
     * @param int|null $tag Tag to identify this animation container
     */
    public function __construct(
        public ?array $animations = [],
        public ?int   $tag = null
    )
    {
    }
}
