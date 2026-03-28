<?php

namespace VISU\AI\Pathfinding;

class GridGraph
{
    /**
     * @var array<int, array<int, PathNode>>
     */
    private array $nodes = [];

    public function __construct(
        public readonly int $width,
        public readonly int $height,
        public readonly bool $allowDiagonal = true,
    ) {
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $this->nodes[$y][$x] = new PathNode($x, $y);
            }
        }
    }

    public function getNode(int $x, int $y): ?PathNode
    {
        return $this->nodes[$y][$x] ?? null;
    }

    public function setWalkable(int $x, int $y, bool $walkable): void
    {
        if (isset($this->nodes[$y][$x])) {
            // recreate node to preserve readonly walkable
            $this->nodes[$y][$x] = new PathNode($x, $y, $walkable);
        }
    }

    public function isInBounds(int $x, int $y): bool
    {
        return $x >= 0 && $x < $this->width && $y >= 0 && $y < $this->height;
    }

    /**
     * @return array<PathNode>
     */
    public function getNeighbors(PathNode $node): array
    {
        $neighbors = [];
        $dirs = [
            [0, -1], [0, 1], [-1, 0], [1, 0], // cardinal
        ];

        if ($this->allowDiagonal) {
            $dirs[] = [-1, -1];
            $dirs[] = [1, -1];
            $dirs[] = [-1, 1];
            $dirs[] = [1, 1];
        }

        foreach ($dirs as [$dx, $dy]) {
            $nx = $node->x + $dx;
            $ny = $node->y + $dy;

            if (!$this->isInBounds($nx, $ny)) {
                continue;
            }

            $neighbor = $this->nodes[$ny][$nx];
            if (!$neighbor->walkable) {
                continue;
            }

            // for diagonal: check that both cardinal neighbors are walkable (no corner cutting)
            if ($dx !== 0 && $dy !== 0 && $this->allowDiagonal) {
                $cardX = $this->nodes[$node->y][$nx] ?? null;
                $cardY = $this->nodes[$ny][$node->x] ?? null;
                if ($cardX === null || !$cardX->walkable || $cardY === null || !$cardY->walkable) {
                    continue;
                }
            }

            $neighbors[] = $neighbor;
        }

        return $neighbors;
    }

    public function resetNodes(): void
    {
        for ($y = 0; $y < $this->height; $y++) {
            for ($x = 0; $x < $this->width; $x++) {
                $this->nodes[$y][$x]->reset();
            }
        }
    }
}
