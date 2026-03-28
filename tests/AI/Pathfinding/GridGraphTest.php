<?php

namespace Tests\AI\Pathfinding;

use PHPUnit\Framework\TestCase;
use VISU\AI\Pathfinding\GridGraph;

class GridGraphTest extends TestCase
{
    public function testConstruction(): void
    {
        $grid = new GridGraph(10, 8);
        $this->assertEquals(10, $grid->width);
        $this->assertEquals(8, $grid->height);
    }

    public function testGetNode(): void
    {
        $grid = new GridGraph(5, 5);
        $node = $grid->getNode(2, 3);

        $this->assertNotNull($node);
        $this->assertEquals(2, $node->x);
        $this->assertEquals(3, $node->y);
        $this->assertTrue($node->walkable);
    }

    public function testOutOfBounds(): void
    {
        $grid = new GridGraph(5, 5);
        $this->assertNull($grid->getNode(-1, 0));
        $this->assertNull($grid->getNode(5, 0));
        $this->assertNull($grid->getNode(0, 5));
    }

    public function testSetWalkable(): void
    {
        $grid = new GridGraph(5, 5);
        $grid->setWalkable(2, 2, false);

        $node = $grid->getNode(2, 2);
        $this->assertNotNull($node);
        $this->assertFalse($node->walkable);
    }

    public function testCardinalNeighbors(): void
    {
        $grid = new GridGraph(3, 3, allowDiagonal: false);
        $center = $grid->getNode(1, 1);
        $this->assertNotNull($center);

        $neighbors = $grid->getNeighbors($center);
        $this->assertCount(4, $neighbors); // N, S, W, E
    }

    public function testDiagonalNeighbors(): void
    {
        $grid = new GridGraph(3, 3, allowDiagonal: true);
        $center = $grid->getNode(1, 1);
        $this->assertNotNull($center);

        $neighbors = $grid->getNeighbors($center);
        $this->assertCount(8, $neighbors); // 4 cardinal + 4 diagonal
    }

    public function testCornerNeighbors(): void
    {
        $grid = new GridGraph(3, 3, allowDiagonal: false);
        $corner = $grid->getNode(0, 0);
        $this->assertNotNull($corner);

        $neighbors = $grid->getNeighbors($corner);
        $this->assertCount(2, $neighbors); // only right and down
    }

    public function testWalkableNeighborsExcluded(): void
    {
        $grid = new GridGraph(3, 3, allowDiagonal: false);
        $grid->setWalkable(1, 0, false);
        $grid->setWalkable(0, 1, false);

        $corner = $grid->getNode(0, 0);
        $this->assertNotNull($corner);

        $neighbors = $grid->getNeighbors($corner);
        $this->assertCount(0, $neighbors);
    }

    public function testIsInBounds(): void
    {
        $grid = new GridGraph(5, 5);
        $this->assertTrue($grid->isInBounds(0, 0));
        $this->assertTrue($grid->isInBounds(4, 4));
        $this->assertFalse($grid->isInBounds(-1, 0));
        $this->assertFalse($grid->isInBounds(5, 0));
    }
}
