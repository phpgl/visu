<?php

namespace VISU\Geo;

use GL\Math\Vec3;

class Ray
{
    /**
     * Constructs a Ray
     * 
     * @param Vec3 $origin
     * @param Vec3 $direction
     */
    public function __construct(
        public Vec3 $origin,
        public Vec3 $direction
    )
    {
        
    }

    public function copy() : Ray
    {
        return new Ray($this->origin, $this->direction);
    }

    public function pointAt(float $distance) : Vec3
    {
        return $this->origin + ($this->direction * $distance);
    }
}
