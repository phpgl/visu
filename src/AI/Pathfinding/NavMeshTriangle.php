<?php

namespace VISU\AI\Pathfinding;

use GL\Math\Vec3;

class NavMeshTriangle
{
    public readonly Vec3 $center;

    /**
     * @var array<int> Adjacent triangle indices
     */
    public array $neighbors = [];

    public function __construct(
        public readonly int $index,
        public readonly Vec3 $v0,
        public readonly Vec3 $v1,
        public readonly Vec3 $v2,
    ) {
        $this->center = new Vec3(
            ($v0->x + $v1->x + $v2->x) / 3.0,
            ($v0->y + $v1->y + $v2->y) / 3.0,
            ($v0->z + $v1->z + $v2->z) / 3.0,
        );
    }

    public function containsPoint(float $x, float $z): bool
    {
        return self::pointInTriangle2D(
            $x, $z,
            $this->v0->x, $this->v0->z,
            $this->v1->x, $this->v1->z,
            $this->v2->x, $this->v2->z,
        );
    }

    private static function pointInTriangle2D(
        float $px, float $pz,
        float $ax, float $az,
        float $bx, float $bz,
        float $cx, float $cz,
    ): bool {
        $d1 = self::sign2D($px, $pz, $ax, $az, $bx, $bz);
        $d2 = self::sign2D($px, $pz, $bx, $bz, $cx, $cz);
        $d3 = self::sign2D($px, $pz, $cx, $cz, $ax, $az);

        $hasNeg = ($d1 < 0) || ($d2 < 0) || ($d3 < 0);
        $hasPos = ($d1 > 0) || ($d2 > 0) || ($d3 > 0);

        return !($hasNeg && $hasPos);
    }

    private static function sign2D(
        float $px, float $pz,
        float $ax, float $az,
        float $bx, float $bz,
    ): float {
        return ($px - $bx) * ($az - $bz) - ($ax - $bx) * ($pz - $bz);
    }
}
