<?php

namespace Tests\AI\Pathfinding;

use PHPUnit\Framework\TestCase;
use VISU\AI\Pathfinding\AStarPathfinder;
use VISU\AI\Pathfinding\GridGraph;

class AStarPathfinderTest extends TestCase
{
    public function testSimplePath(): void
    {
        $grid = new GridGraph(5, 5, allowDiagonal: false);
        $pathfinder = new AStarPathfinder();

        $path = $pathfinder->findPath($grid, 0, 0, 4, 4);

        $this->assertNotNull($path);
        $this->assertEquals(0, $path[0]->x);
        $this->assertEquals(0, $path[0]->y);
        $this->assertEquals(4, $path[count($path) - 1]->x);
        $this->assertEquals(4, $path[count($path) - 1]->y);

        // Manhattan path should be 9 steps (4 right + 4 down + start)
        $this->assertCount(9, $path);
    }

    public function testDiagonalPath(): void
    {
        $grid = new GridGraph(5, 5, allowDiagonal: true);
        $pathfinder = new AStarPathfinder();

        $path = $pathfinder->findPath($grid, 0, 0, 4, 4);

        $this->assertNotNull($path);
        // diagonal should be 5 steps (start + 4 diagonal)
        $this->assertCount(5, $path);
    }

    public function testPathAroundObstacle(): void
    {
        // 5x5 grid with a wall across the middle
        $grid = new GridGraph(5, 5, allowDiagonal: false);
        $grid->setWalkable(0, 2, false);
        $grid->setWalkable(1, 2, false);
        $grid->setWalkable(2, 2, false);
        $grid->setWalkable(3, 2, false);
        // gap at (4,2)

        $pathfinder = new AStarPathfinder();
        $path = $pathfinder->findPath($grid, 0, 0, 0, 4);

        $this->assertNotNull($path);
        // must go around the wall through (4,2)
        $found = false;
        foreach ($path as $node) {
            if ($node->x === 4 && $node->y === 2) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Path should go through the gap at (4,2)');
    }

    public function testNoPath(): void
    {
        // completely blocked
        $grid = new GridGraph(3, 3, allowDiagonal: false);
        $grid->setWalkable(1, 0, false);
        $grid->setWalkable(0, 1, false);
        $grid->setWalkable(1, 1, false);

        $pathfinder = new AStarPathfinder();
        $path = $pathfinder->findPath($grid, 0, 0, 2, 2);

        $this->assertNull($path);
    }

    public function testStartEqualsGoal(): void
    {
        $grid = new GridGraph(3, 3);
        $pathfinder = new AStarPathfinder();

        $path = $pathfinder->findPath($grid, 1, 1, 1, 1);

        $this->assertNotNull($path);
        $this->assertCount(1, $path);
    }

    public function testUnwalkableStart(): void
    {
        $grid = new GridGraph(3, 3);
        $grid->setWalkable(0, 0, false);

        $pathfinder = new AStarPathfinder();
        $this->assertNull($pathfinder->findPath($grid, 0, 0, 2, 2));
    }

    public function testUnwalkableGoal(): void
    {
        $grid = new GridGraph(3, 3);
        $grid->setWalkable(2, 2, false);

        $pathfinder = new AStarPathfinder();
        $this->assertNull($pathfinder->findPath($grid, 0, 0, 2, 2));
    }

    public function testOutOfBounds(): void
    {
        $grid = new GridGraph(3, 3);
        $pathfinder = new AStarPathfinder();

        $this->assertNull($pathfinder->findPath($grid, -1, 0, 2, 2));
        $this->assertNull($pathfinder->findPath($grid, 0, 0, 5, 5));
    }

    public function testNoDiagonalCornerCutting(): void
    {
        // 3x3 grid with walls creating a corner
        $grid = new GridGraph(3, 3, allowDiagonal: true);
        $grid->setWalkable(1, 0, false);
        $grid->setWalkable(0, 1, false);

        $pathfinder = new AStarPathfinder();
        // (0,0) to (1,1) - diagonal should be blocked by corner cutting prevention
        $path = $pathfinder->findPath($grid, 0, 0, 2, 2);

        // Should be null — (0,0) is completely surrounded by walls on the paths
        $this->assertNull($path);
    }
}
