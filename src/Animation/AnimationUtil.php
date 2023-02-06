<?php

namespace VISU\Animation;

use GL\Math\Quat;
use GL\Math\Vec3;

class AnimationUtil
{
    /**
     * @param Vec3 $distance The distance to travel
     * @param int $mph The speed in miles per hour
     * @param int $limit The minimum duration in milliseconds
     * @return int The duration in milliseconds
     */
    public static function calculatePositionTransitionDuration(Vec3 $distance, int $mph, int $limit = 0): int
    {
        // 1 mile = 1609.344 meters
        // 1 unit = 1 meter
        $distanceInMiles = $distance->length() / 1609.344;
        $timeInMinutes = ($distanceInMiles / $mph) * 60;
        $timeInMilliseconds = $timeInMinutes * 1000;
        return max(ceil($timeInMilliseconds), $limit);
    }

    /**
     * Calculates the duration of a rotation animation
     *
     * @param Quat $orientation The orientation to rotate to
     * @param int $degreesPerSecond The speed in degrees per second
     * @param int $limit The minimum duration in milliseconds
     * @return int The duration in milliseconds
     */
    public static function calculateOrientationTransitionDuration(Quat $orientation, int $degreesPerSecond, int $limit = 0): int
    {
        $normalizedOrientation = Quat::normalized($orientation);
        $angleInDegrees = rad2deg(acos($normalizedOrientation->w) * 2);
        // If the angle is greater than 180 degrees, then we need to rotate the other shorter way
        if ($angleInDegrees > 180) {
            $angleInDegrees = 360 - $angleInDegrees;
        }
        $timeInMilliseconds = ($angleInDegrees / $degreesPerSecond) * 1000;
        return max(ceil($timeInMilliseconds), $limit);
    }
}
