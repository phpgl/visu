<?php

namespace VISU\Geo;

use GL\Math\Mat4;
use GL\Math\Vec3;

class Frustum
{
    public static function fromMat4(Mat4 $mat) : self 
    {
        // Gribb/Hartmann method
        return new self(
            left: new Plane(
                new Vec3(
                    $mat[3] + $mat[0], // a
                    $mat[7] + $mat[4], // b
                    $mat[11] + $mat[8] // c
                ),
                $mat[15] + $mat[12] // d
            ),
            right: new Plane(
                new Vec3(
                    $mat[3] - $mat[0], // a
                    $mat[7] - $mat[4], // b
                    $mat[11] - $mat[8] // c
                ),
                $mat[15] - $mat[12] // d
            ),
            top: new Plane(
                new Vec3(
                    $mat[3] - $mat[1], // a
                    $mat[7] - $mat[5], // b
                    $mat[11] - $mat[9] // c
                ),
                $mat[15] - $mat[13] // d
            ),
            bottom: new Plane(
                new Vec3(
                    $mat[3] + $mat[1], // a
                    $mat[7] + $mat[5], // b
                    $mat[11] + $mat[9] // c
                ),
                $mat[15] + $mat[13] // d
            ),
            near: new Plane(
                new Vec3(
                    $mat[3] + $mat[2], // a
                    $mat[7] + $mat[6], // b
                    $mat[11] + $mat[10] // c
                ),
                $mat[15] + $mat[14] // d
            ),
            far: new Plane(
                new Vec3(
                    $mat[3] - $mat[2], // a
                    $mat[7] - $mat[6], // b
                    $mat[11] - $mat[10] // c
                ),
                $mat[15] - $mat[14] // d
            )
        );
    }

    /**
     * Holds an additional reference to the planes that can be accessed by index
     * 
     * @var array<Plane>
     */
    private array $planes = [];

    /**
     * Frustum is represented by 6 planes
     */
    public function __construct(
        public Plane $left,
        public Plane $right,
        public Plane $top,
        public Plane $bottom,
        public Plane $near,
        public Plane $far
    )
    {
        foreach ([$left, $right, $top, $bottom, $near, $far] as $plane) {
            $this->planes[] = $plane;
        }
    }

    /**
     * Returns boolean if a given sphere is visible in the frustum
     */
    public function isSphereInView(Vec3 $center, float $radius): bool
    {
        foreach ($this->planes as $plane) {
            $distance = Vec3::dot($plane->normal, $center) + $plane->distance;
            if ($distance < -$radius) {
                return false;
            }
        }

        return true;
    }
}
