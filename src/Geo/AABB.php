<?php

namespace VISU\Geo;

use GL\Math\Vec3;

class AABB
{
    /**
     * Constructs an axis aligned bounding box
     * 
     * @param Vec3 $min 
     * @param Vec3 $max 
     */
    public function __construct(
        public Vec3 $min,
        public Vec3 $max
    )
    {
        
    }

    /**
     * Returns the center of the AABB
     */
    public function getCenter() : Vec3
    {
        return ($this->min + $this->max) * 0.5;
    }

    /**
     * Retuns the intersection of this AABB and Ray
     * 
     * @param Ray $ray
     * @return float|null
     */
    public function intersectRay(Ray $ray) : ?float
    {
        $tmin = ($this->min->x - $ray->origin->x) / $ray->direction->x;
        $tmax = ($this->max->x - $ray->origin->x) / $ray->direction->x;

        if ($tmin > $tmax) {
            $tmp = $tmin;
            $tmin = $tmax;
            $tmax = $tmp;
        }

        $tymin = ($this->min->y - $ray->origin->y) / $ray->direction->y;
        $tymax = ($this->max->y - $ray->origin->y) / $ray->direction->y;

        if ($tymin > $tymax) {
            $tmp = $tymin;
            $tymin = $tymax;
            $tymax = $tmp;
        }

        if (($tmin > $tymax) || ($tymin > $tmax)) {
            return null;
        }

        if ($tymin > $tmin) {
            $tmin = $tymin;
        }

        if ($tymax < $tmax) {
            $tmax = $tymax;
        }

        $tzmin = ($this->min->z - $ray->origin->z) / $ray->direction->z;
        $tzmax = ($this->max->z - $ray->origin->z) / $ray->direction->z;

        if ($tzmin > $tzmax) {
            $tmp = $tzmin;
            $tzmin = $tzmax;
            $tzmax = $tmp;
        }

        if (($tmin > $tzmax) || ($tzmin > $tmax)) {
            return null;
        }

        if ($tzmin > $tmin) {
            $tmin = $tzmin;
        }

        if ($tzmax < $tmax) {
            $tmax = $tzmax;
        }

        return $tmin;
    }
}
