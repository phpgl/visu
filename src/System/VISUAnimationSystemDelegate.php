<?php

namespace VISU\System;

use VISU\Animation\BaseAnimation;
use VISU\Animation\BaseAnimationContainer;

/**
 * Interface for animation system delegates
 */
interface VISUAnimationSystemDelegate
{
    /**
     * Called when an animation starts
     *
     * @param BaseAnimation $animation The animation
     * @param int $entity The entity the animation is running on
     * @param bool $reversing Is the animation reversing?
     * @param bool $repeating Is the animation repeating?
     * @param bool $waiting Is the animation waiting?
     * @return void
     */
    public function animationDidStart(BaseAnimation $animation, int $entity, bool $reversing, bool $repeating, bool $waiting): void;

    /**
     * Called when an animation stops
     *
     * @param BaseAnimation $animation
     * @param int $entity
     * @param bool $finished
     * @return void
     */
    public function animationDidStop(BaseAnimation $animation, int $entity, bool $finished): void;

    /**
     * Called when an animation group begins
     *
     * @param BaseAnimationContainer $animationGroup The animation group
     * @param int $entity The entity the animation group is running on
     * @return void
     */
    public function animationGroupDidBegin(BaseAnimationContainer $animationGroup, int $entity): void;

    /**
     * Called when an animation group finishes
     *
     * @param BaseAnimationContainer $animationGroup The animation group
     * @param int $entity The entity the animation group is running on
     * @return void
     */
    public function animationGroupDidFinish(BaseAnimationContainer $animationGroup, int $entity): void;
}
