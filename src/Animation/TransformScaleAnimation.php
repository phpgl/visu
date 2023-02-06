<?php

namespace VISU\Animation;

use GL\Math\Vec3;

/**
 * Animation that scales an object with a given scale modifier
 */
class TransformScaleAnimation extends BaseAnimation
{
    public ?Vec3 $initialScale;
    public ?Vec3 $targetScale;

    /**
     * @param Vec3 $modifier The scale modifier to apply
     * @param int $duration The duration of the animation in milliseconds
     * @param AnimationEasingType|null $easingType The easing type
     * @param int|null $initialDelay The initial delay in milliseconds
     * @param bool $repeat Whether the animation should repeat
     * @param int|null $repeatCount The number of times the animation should repeat
     * @param int|null $repeatDelay The delay between each repeat in milliseconds
     * @param bool $reverse Whether the animation should reverse
     * @param int|null $reverseCount The number of times the animation should reverse
     * @param int|null $reverseDelay The delay between each reverse in milliseconds
     * @param int|null $tag The tag of the animation
     */
    public function __construct(
        public Vec3          $modifier,
        int                  $duration,
        ?AnimationEasingType $easingType = AnimationEasingType::LINEAR,
        ?int                 $initialDelay = 0,
        ?bool                $repeat = false,
        ?int                 $repeatCount = 0,
        ?int                 $repeatDelay = 0,
        ?bool                $reverse = false,
        ?int                 $reverseCount = 0,
        ?int                 $reverseDelay = 0,
        ?int                 $tag = null
    )
    {
        parent::__construct(
            $duration,
            $easingType,
            $initialDelay,
            $repeat,
            $repeatCount,
            $repeatDelay,
            $reverse,
            $reverseCount,
            $reverseDelay,
            $tag
        );
    }

    /**
     * Creates a new animation from the current and target scale
     *
     * @param Vec3 $currentScale
     * @param Vec3 $targetScale
     * @param int $duration
     * @return static
     */
    public static function fromCurrentAndTargetScale(Vec3 $currentScale, Vec3 $targetScale, int $duration): self
    {
        return new self($targetScale / $currentScale, $duration);
    }
}
