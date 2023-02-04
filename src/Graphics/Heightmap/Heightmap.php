<?php

namespace VISU\Graphics\Heightmap;

use GL\Buffer\FloatBuffer;
use GL\Math\Vec3;
use VISU\Geo\Ray;

class Heightmap
{
    public function __construct(
        private FloatBuffer $data,
        private int $width,
        private int $height,
        private float $ppu = 1.0,
    )
    {
        
    }

    /**
     * Returns the height at the given world space x and z coordinates
     * 
     * @param float $x 
     * @param float $y 
     * @return float 
     */
    public function getHeightAt(float $x, float $y) : ?float
    {
        $x = $x * $this->ppu;
        $y = $y * $this->ppu;

        $x = $x + $this->width / 2;
        $y = $y + $this->height / 2;

        $x = (int) $x;
        $y = (int) $y;

        $index = $y * $this->width + $x;

        return $this->data[$index];
    }

    /**
     * Casts a ray against the heightmap and returns the intersection point (binary search)
     * 
     * @param Ray $ray
     * @return Vec3|null 
     */
    public function castRay(Ray $ray, float $maxDistance = 1000, int $lookups = 16) : ?Vec3
    {
        $r = $ray->copy();
        $testpoint = $ray->origin->copy();

        for ($i = 0; $i < $lookups; $i++) {
            $maxDistance *= 0.5;

            $testpoint = $r->pointAt($maxDistance);

            if ($this->getHeightAt($testpoint->x, $testpoint->z) < $testpoint->y) {
                $r->origin = $testpoint;
            }
        }

        return $testpoint;
    }
}