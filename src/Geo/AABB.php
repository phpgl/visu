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
     * Returns the width of the AABB
     */
    public function width() : float
    {
        return $this->max->x - $this->min->x;
    }

    /**
     * Returns the height of the AABB
     */
    public function height() : float
    {
        return $this->max->y - $this->min->y;
    }

    /**
     * Returns the depth of the AABB
     */
    public function depth() : float
    {
        return $this->max->z - $this->min->z;
    }

    /**
     * Returns a copy of the current AABB
     */
    public function copy() : AABB 
    {
        return new AABB(
            $this->min->copy(),
            $this->max->copy()
        );
    }

    /**
     * Returns the center of the AABB
     */
    public function getCenter() : float|Vec3
    {
        return ($this->min + $this->max) * 0.5;
    }

    /**
     * Returns the intersection point of the current AABB and the given Ray
     *
     * @param Ray $ray
     * @return Vec3|null
     */
    public function intersectRay(Ray $ray) : ?Vec3
    {
        $t = $this->intersectRayDistance($ray);

        if ($t === null) {
            return null;
        }

        return $ray->origin + $ray->direction * $t;
    }

    /**
     * Extends the current AABB to include the given AABB
     */
    public function extend(AABB $aabb) : void
    {
        $this->min->x = min($this->min->x, $aabb->min->x);
        $this->min->y = min($this->min->y, $aabb->min->y);
        $this->min->z = min($this->min->z, $aabb->min->z);

        $this->max->x = max($this->max->x, $aabb->max->x);
        $this->max->y = max($this->max->y, $aabb->max->y);
        $this->max->z = max($this->max->z, $aabb->max->z);
    }

    /**
     * Retuns the intersection distance of this AABB and Ray
     * 
     * @param Ray $ray
     * @return float|null
     */
    public function intersectRayDistance(Ray $ray) : ?float
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
