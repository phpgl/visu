<?php

namespace VISU\Signals\ECS;

use VISU\Signal\Signal;

class CollisionSignal extends Signal
{
    public function __construct(
        public readonly int $entityA,
        public readonly int $entityB,
        public readonly float $contactX = 0.0,
        public readonly float $contactY = 0.0,
        public readonly bool $isTrigger = false,
    ) {
    }
}
