<?php

namespace VISU\AI\Pathfinding;

class PathNode
{
    public float $gCost = PHP_FLOAT_MAX;
    public float $hCost = 0.0;
    public ?PathNode $parent = null;
    public bool $closed = false;

    public function __construct(
        public readonly int $x,
        public readonly int $y,
        public readonly bool $walkable = true,
    ) {
    }

    public function fCost(): float
    {
        return $this->gCost + $this->hCost;
    }

    public function reset(): void
    {
        $this->gCost = PHP_FLOAT_MAX;
        $this->hCost = 0.0;
        $this->parent = null;
        $this->closed = false;
    }
}
