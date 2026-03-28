<?php

namespace VISU\AI\Pathfinding;

use GL\Math\Vec3;

class NavMesh
{
    /**
     * @var array<NavMeshTriangle>
     */
    private array $triangles = [];

    /**
     * Add a triangle to the navmesh.
     */
    public function addTriangle(Vec3 $v0, Vec3 $v1, Vec3 $v2): int
    {
        $index = count($this->triangles);
        $this->triangles[] = new NavMeshTriangle($index, $v0, $v1, $v2);
        return $index;
    }

    /**
     * Connect two triangles as neighbors (bidirectional).
     */
    public function connectTriangles(int $a, int $b): void
    {
        if (!isset($this->triangles[$a]) || !isset($this->triangles[$b])) {
            return;
        }

        if (!in_array($b, $this->triangles[$a]->neighbors, true)) {
            $this->triangles[$a]->neighbors[] = $b;
        }
        if (!in_array($a, $this->triangles[$b]->neighbors, true)) {
            $this->triangles[$b]->neighbors[] = $a;
        }
    }

    /**
     * Auto-detect shared edges and connect triangles.
     * Two triangles sharing 2 vertices are considered neighbors.
     */
    public function buildConnectivity(float $epsilon = 0.001): void
    {
        $count = count($this->triangles);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($this->sharesEdge($this->triangles[$i], $this->triangles[$j], $epsilon)) {
                    $this->connectTriangles($i, $j);
                }
            }
        }
    }

    private function sharesEdge(NavMeshTriangle $a, NavMeshTriangle $b, float $epsilon): bool
    {
        $vertsA = [$a->v0, $a->v1, $a->v2];
        $vertsB = [$b->v0, $b->v1, $b->v2];

        $shared = 0;
        foreach ($vertsA as $va) {
            foreach ($vertsB as $vb) {
                $dx = $va->x - $vb->x;
                $dy = $va->y - $vb->y;
                $dz = $va->z - $vb->z;
                if (($dx * $dx + $dy * $dy + $dz * $dz) < $epsilon * $epsilon) {
                    $shared++;
                    if ($shared >= 2) {
                        return true;
                    }
                    break;
                }
            }
        }

        return false;
    }

    /**
     * Find which triangle contains the given XZ position.
     */
    public function findTriangle(float $x, float $z): ?NavMeshTriangle
    {
        foreach ($this->triangles as $tri) {
            if ($tri->containsPoint($x, $z)) {
                return $tri;
            }
        }
        return null;
    }

    /**
     * Find a path between two world positions using A* on the triangle graph.
     *
     * @return array<Vec3>|null Waypoints from start to goal (triangle centers + goal)
     */
    public function findPath(Vec3 $start, Vec3 $goal): ?array
    {
        $startTri = $this->findTriangle($start->x, $start->z);
        $goalTri = $this->findTriangle($goal->x, $goal->z);

        if ($startTri === null || $goalTri === null) {
            return null;
        }

        if ($startTri->index === $goalTri->index) {
            return [$start, $goal];
        }

        // A* on triangle graph
        /** @var array<int, float> */
        $gScore = [];
        /** @var array<int, int> */
        $cameFrom = [];
        /** @var array<int, float> */
        $openSet = [];

        $gScore[$startTri->index] = 0.0;
        $openSet[$startTri->index] = $this->distVec3($start, $startTri->center) +
            $this->distVec3($startTri->center, $goalTri->center);

        /** @var array<int, bool> */
        $closedSet = [];

        while (count($openSet) > 0) {
            // get lowest fScore
            $currentIdx = $this->getLowestF($openSet);
            unset($openSet[$currentIdx]);

            if ($currentIdx === $goalTri->index) {
                return $this->reconstructNavPath($cameFrom, $currentIdx, $start, $goal);
            }

            $closedSet[$currentIdx] = true;
            $current = $this->triangles[$currentIdx];

            foreach ($current->neighbors as $neighborIdx) {
                if (isset($closedSet[$neighborIdx])) {
                    continue;
                }

                $neighbor = $this->triangles[$neighborIdx];
                $tentativeG = ($gScore[$currentIdx] ?? PHP_FLOAT_MAX)
                    + $this->distVec3($current->center, $neighbor->center);

                if ($tentativeG < ($gScore[$neighborIdx] ?? PHP_FLOAT_MAX)) {
                    $gScore[$neighborIdx] = $tentativeG;
                    $cameFrom[$neighborIdx] = $currentIdx;
                    $openSet[$neighborIdx] = $tentativeG + $this->distVec3($neighbor->center, $goalTri->center);
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, float> $openSet
     */
    private function getLowestF(array $openSet): int
    {
        $bestIdx = -1;
        $bestF = PHP_FLOAT_MAX;

        foreach ($openSet as $idx => $f) {
            if ($f < $bestF) {
                $bestF = $f;
                $bestIdx = $idx;
            }
        }

        return $bestIdx;
    }

    /**
     * @param array<int, int> $cameFrom
     * @return array<Vec3>
     */
    private function reconstructNavPath(array $cameFrom, int $currentIdx, Vec3 $start, Vec3 $goal): array
    {
        $indices = [$currentIdx];
        while (isset($cameFrom[$currentIdx])) {
            $currentIdx = $cameFrom[$currentIdx];
            $indices[] = $currentIdx;
        }

        $indices = array_reverse($indices);

        $path = [$start];
        // skip first and last triangle centers (start/goal replace them)
        for ($i = 1; $i < count($indices) - 1; $i++) {
            $path[] = $this->triangles[$indices[$i]]->center;
        }
        $path[] = $goal;

        return $path;
    }

    private function distVec3(Vec3 $a, Vec3 $b): float
    {
        $dx = $a->x - $b->x;
        $dy = $a->y - $b->y;
        $dz = $a->z - $b->z;
        return sqrt($dx * $dx + $dy * $dy + $dz * $dz);
    }

    public function getTriangleCount(): int
    {
        return count($this->triangles);
    }

    public function getTriangle(int $index): ?NavMeshTriangle
    {
        return $this->triangles[$index] ?? null;
    }
}
