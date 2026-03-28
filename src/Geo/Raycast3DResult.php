<?php

namespace VISU\Geo;

use GL\Math\Vec3;

class Raycast3DResult
{
    public function __construct(
        public readonly int $entityId,
        public readonly float $distance,
        public readonly Vec3 $hitPoint,
        public readonly Vec3 $hitNormal,
    ) {
    }
}
