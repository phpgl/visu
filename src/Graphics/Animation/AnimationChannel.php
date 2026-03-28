<?php

namespace VISU\Graphics\Animation;

use GL\Math\{Quat, Vec3};

class AnimationChannel
{
    /**
     * Target bone index in the skeleton
     */
    public readonly int $boneIndex;

    /**
     * Target property: 'translation', 'rotation', or 'scale'
     */
    public readonly string $property;

    /**
     * Interpolation mode
     */
    public AnimationInterpolation $interpolation = AnimationInterpolation::Linear;

    /**
     * Keyframe timestamps in seconds
     * @var array<float>
     */
    public array $times = [];

    /**
     * Keyframe values (Vec3 for translation/scale, Quat for rotation)
     * @var array<Vec3|Quat>
     */
    public array $values = [];

    public function __construct(int $boneIndex, string $property)
    {
        $this->boneIndex = $boneIndex;
        $this->property = $property;
    }

    /**
     * Samples the channel at the given time, returning the interpolated value.
     */
    public function sample(float $time): Vec3|Quat
    {
        $count = count($this->times);

        if ($count === 0) {
            return $this->property === 'rotation'
                ? new Quat()
                : new Vec3(0, 0, 0);
        }

        // before first keyframe
        if ($time <= $this->times[0]) {
            return $this->cloneValue($this->values[0]);
        }

        // after last keyframe
        if ($time >= $this->times[$count - 1]) {
            return $this->cloneValue($this->values[$count - 1]);
        }

        // find surrounding keyframes
        for ($i = 0; $i < $count - 1; $i++) {
            if ($time >= $this->times[$i] && $time < $this->times[$i + 1]) {
                if ($this->interpolation === AnimationInterpolation::Step) {
                    return $this->cloneValue($this->values[$i]);
                }

                // linear interpolation
                $t0 = $this->times[$i];
                $t1 = $this->times[$i + 1];
                $factor = ($time - $t0) / ($t1 - $t0);

                return $this->interpolateValues($this->values[$i], $this->values[$i + 1], $factor);
            }
        }

        return $this->cloneValue($this->values[$count - 1]);
    }

    private function interpolateValues(Vec3|Quat $a, Vec3|Quat $b, float $t): Vec3|Quat
    {
        if ($a instanceof Quat && $b instanceof Quat) {
            return Quat::slerp($a, $b, $t);
        }

        if ($a instanceof Vec3 && $b instanceof Vec3) {
            return new Vec3(
                $a->x + ($b->x - $a->x) * $t,
                $a->y + ($b->y - $a->y) * $t,
                $a->z + ($b->z - $a->z) * $t,
            );
        }

        return $this->cloneValue($a);
    }

    private function cloneValue(Vec3|Quat $value): Vec3|Quat
    {
        if ($value instanceof Quat) {
            return new Quat($value->w, $value->x, $value->y, $value->z);
        }
        return new Vec3($value->x, $value->y, $value->z);
    }
}
