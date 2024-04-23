<?php

namespace VISU\Geo;

use GL\Math\Vec3;

class Plane
{
    public function __construct(
        public Vec3 $normal,
        public float $distance
    )
    {
        $mag = $this->normal->length();
        $this->normal = $this->normal / $mag;
        $this->distance /= $mag;
    }
}