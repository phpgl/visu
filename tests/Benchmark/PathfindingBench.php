<?php

namespace VISU\Tests\Benchmark;

use GL\Math\Vec3;
use VISU\AI\Pathfinding\AStarPathfinder;
use VISU\AI\Pathfinding\GridGraph;
use VISU\AI\Pathfinding\NavMesh;

class PathfindingBench
{
    private GridGraph $smallGrid;
    private GridGraph $largeGrid;
    private GridGraph $mazeGrid;
    private AStarPathfinder $pathfinder;
    private NavMesh $navMesh;

    public function setUp(): void
    {
        $this->pathfinder = new AStarPathfinder();

        // 20x20 open grid
        $this->smallGrid = new GridGraph(20, 20);

        // 100x100 with wall
        $this->largeGrid = new GridGraph(100, 100);
        for ($i = 10; $i < 90; $i++) {
            $this->largeGrid->setWalkable($i, 50, false);
        }

        // 50x50 maze-like pattern
        $this->mazeGrid = new GridGraph(50, 50);
        for ($y = 0; $y < 50; $y += 4) {
            for ($x = 0; $x < 48; $x++) {
                $this->mazeGrid->setWalkable($x, $y, false);
            }
        }
        for ($y = 2; $y < 50; $y += 4) {
            for ($x = 2; $x < 50; $x++) {
                $this->mazeGrid->setWalkable($x, $y, false);
            }
        }

        // NavMesh: 10x10 grid of triangles (200 triangles)
        $this->navMesh = new NavMesh();
        for ($z = 0; $z < 10; $z++) {
            for ($x = 0; $x < 10; $x++) {
                $fx = (float)$x * 10.0;
                $fz = (float)$z * 10.0;
                $this->navMesh->addTriangle(
                    new Vec3($fx, 0, $fz),
                    new Vec3($fx + 10, 0, $fz),
                    new Vec3($fx, 0, $fz + 10),
                );
                $this->navMesh->addTriangle(
                    new Vec3($fx + 10, 0, $fz),
                    new Vec3($fx + 10, 0, $fz + 10),
                    new Vec3($fx, 0, $fz + 10),
                );
            }
        }
        $this->navMesh->buildConnectivity();
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchAStarSmallGrid20x20(): void
    {
        $this->pathfinder->findPath($this->smallGrid, 0, 0, 19, 19);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(100)
     * @Iterations(5)
     */
    public function benchAStarLargeGrid100x100(): void
    {
        $this->pathfinder->findPath($this->largeGrid, 0, 0, 99, 99);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(100)
     * @Iterations(5)
     */
    public function benchAStarMaze50x50(): void
    {
        $this->pathfinder->findPath($this->mazeGrid, 0, 0, 49, 49);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(100)
     * @Iterations(5)
     */
    public function benchNavMeshFindPath(): void
    {
        $start = new Vec3(5, 0, 5);
        $goal = new Vec3(95, 0, 95);
        $this->navMesh->findPath($start, $goal);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchNavMeshFindTriangle(): void
    {
        $this->navMesh->findTriangle(55.0, 55.0);
    }
}
