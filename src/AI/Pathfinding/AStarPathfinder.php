<?php

namespace VISU\AI\Pathfinding;

class AStarPathfinder
{
    /**
     * Find a path from start to goal on the given grid.
     *
     * @return array<PathNode>|null Array of path nodes (start to goal) or null if no path
     */
    public function findPath(GridGraph $grid, int $startX, int $startY, int $goalX, int $goalY): ?array
    {
        $grid->resetNodes();

        $start = $grid->getNode($startX, $startY);
        $goal = $grid->getNode($goalX, $goalY);

        if ($start === null || $goal === null) {
            return null;
        }

        if (!$start->walkable || !$goal->walkable) {
            return null;
        }

        $start->gCost = 0.0;
        $start->hCost = $this->heuristic($start, $goal);

        /** @var array<string, PathNode> $openSet keyed by "x,y" */
        $openSet = [];
        $openSet["{$start->x},{$start->y}"] = $start;

        while (count($openSet) > 0) {
            // find node with lowest fCost
            $current = $this->getLowestFCost($openSet);
            $key = "{$current->x},{$current->y}";
            unset($openSet[$key]);
            $current->closed = true;

            // reached goal
            if ($current === $goal) {
                return $this->reconstructPath($goal);
            }

            foreach ($grid->getNeighbors($current) as $neighbor) {
                if ($neighbor->closed) {
                    continue;
                }

                $moveCost = $this->movementCost($current, $neighbor);
                $tentativeG = $current->gCost + $moveCost;

                if ($tentativeG < $neighbor->gCost) {
                    $neighbor->gCost = $tentativeG;
                    $neighbor->hCost = $this->heuristic($neighbor, $goal);
                    $neighbor->parent = $current;

                    $nKey = "{$neighbor->x},{$neighbor->y}";
                    if (!isset($openSet[$nKey])) {
                        $openSet[$nKey] = $neighbor;
                    }
                }
            }
        }

        return null; // no path found
    }

    private function heuristic(PathNode $a, PathNode $b): float
    {
        // octile distance (exact for 8-directional movement)
        $dx = abs($a->x - $b->x);
        $dy = abs($a->y - $b->y);
        return max($dx, $dy) + (M_SQRT2 - 1) * min($dx, $dy);
    }

    private function movementCost(PathNode $from, PathNode $to): float
    {
        $dx = abs($from->x - $to->x);
        $dy = abs($from->y - $to->y);

        return ($dx + $dy > 1) ? M_SQRT2 : 1.0;
    }

    /**
     * @param array<string, PathNode> $openSet
     */
    private function getLowestFCost(array $openSet): PathNode
    {
        $best = null;
        $bestF = PHP_FLOAT_MAX;

        foreach ($openSet as $node) {
            $f = $node->fCost();
            if ($f < $bestF || ($f === $bestF && $best !== null && $node->hCost < $best->hCost)) {
                $best = $node;
                $bestF = $f;
            }
        }

        assert($best !== null);
        return $best;
    }

    /**
     * @return array<PathNode>
     */
    private function reconstructPath(PathNode $goal): array
    {
        $path = [];
        $current = $goal;

        while ($current !== null) {
            $path[] = $current;
            $current = $current->parent;
        }

        return array_reverse($path);
    }
}
