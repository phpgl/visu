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
}
