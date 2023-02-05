<?php

namespace VISU\Geo;

use GL\Math\GLM;
use GL\Math\Vec3;

/**
 * Holds a bunch of static helper functions 
 * I could not find a better place for... 
 */
class Math
{
    /**
     * Projects a vector onto a plane with the given normal
     * 
     * @param Vec3 $dir 
     * @param Vec3 $normal 
     * @return Vec3 
     */
    public static function projectOnPlane(Vec3 $dir, Vec3 $normal) : Vec3
    {
        return Vec3::normalized($dir - Vec3::dot($dir, $normal) * $normal);
    }
}
