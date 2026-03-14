<?php

namespace VISU\Signals\ECS;

use GL\Math\Vec3;
use VISU\Signal\Signal;

class Collision3DSignal extends Signal
{
    public function __construct(
        public readonly int $entityA,
        public readonly int $entityB,
        public readonly Vec3 $contactPoint,
        public readonly Vec3 $contactNormal,
        public readonly float $penetration,
    ) {
    }
}
