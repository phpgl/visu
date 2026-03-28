<?php

namespace VISU\Geo;

class Raycast2DResult
{
    public function __construct(
        public readonly int $entityId,
        public readonly float $distance,
        public readonly float $hitX,
        public readonly float $hitY,
    ) {
    }
}
