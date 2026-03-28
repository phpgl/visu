<?php

namespace Tests\AI\Pathfinding;

use GL\Math\Vec3;
use PHPUnit\Framework\TestCase;
use VISU\AI\Pathfinding\NavMesh;

class NavMeshTest extends TestCase
{
    public function testAddTriangle(): void
    {
        $mesh = new NavMesh();
        $idx = $mesh->addTriangle(
            new Vec3(0, 0, 0),
            new Vec3(10, 0, 0),
            new Vec3(5, 0, 10),
        );

        $this->assertEquals(0, $idx);
        $this->assertEquals(1, $mesh->getTriangleCount());
    }

    public function testFindTriangle(): void
    {
        $mesh = new NavMesh();
        $mesh->addTriangle(
            new Vec3(0, 0, 0),
            new Vec3(10, 0, 0),
            new Vec3(5, 0, 10),
        );

        // center of triangle
        $tri = $mesh->findTriangle(5.0, 3.0);
        $this->assertNotNull($tri);
        $this->assertEquals(0, $tri->index);

        // outside triangle
        $this->assertNull($mesh->findTriangle(-5.0, -5.0));
    }

    public function testManualConnectivity(): void
    {
        $mesh = new NavMesh();
        $mesh->addTriangle(
            new Vec3(0, 0, 0),
            new Vec3(10, 0, 0),
            new Vec3(5, 0, 10),
        );
        $mesh->addTriangle(
            new Vec3(10, 0, 0),
            new Vec3(20, 0, 0),
            new Vec3(5, 0, 10),
        );

        $mesh->connectTriangles(0, 1);

        $tri0 = $mesh->getTriangle(0);
        $tri1 = $mesh->getTriangle(1);
        $this->assertNotNull($tri0);
        $this->assertNotNull($tri1);
        $this->assertContains(1, $tri0->neighbors);
        $this->assertContains(0, $tri1->neighbors);
    }

    public function testAutoConnectivity(): void
    {
        $mesh = new NavMesh();
        // two triangles sharing an edge (10,0,0)-(5,0,10)
        $mesh->addTriangle(
            new Vec3(0, 0, 0),
            new Vec3(10, 0, 0),
            new Vec3(5, 0, 10),
        );
        $mesh->addTriangle(
            new Vec3(10, 0, 0),
            new Vec3(15, 0, 10),
            new Vec3(5, 0, 10),
        );

        $mesh->buildConnectivity();

        $tri0 = $mesh->getTriangle(0);
        $this->assertNotNull($tri0);
        $this->assertContains(1, $tri0->neighbors);
    }

    public function testFindPathSameTriangle(): void
    {
        $mesh = new NavMesh();
        $mesh->addTriangle(
            new Vec3(0, 0, 0),
            new Vec3(10, 0, 0),
            new Vec3(5, 0, 10),
        );

        $start = new Vec3(3, 0, 2);
        $goal = new Vec3(6, 0, 3);

        $path = $mesh->findPath($start, $goal);
        $this->assertNotNull($path);
        $this->assertCount(2, $path); // start + goal
    }

    public function testFindPathAcrossTriangles(): void
    {
        $mesh = new NavMesh();
        // strip of 3 triangles
        $mesh->addTriangle(new Vec3(0, 0, 0), new Vec3(10, 0, 0), new Vec3(5, 0, 10));
        $mesh->addTriangle(new Vec3(10, 0, 0), new Vec3(20, 0, 0), new Vec3(5, 0, 10));
        $mesh->addTriangle(new Vec3(20, 0, 0), new Vec3(30, 0, 0), new Vec3(25, 0, 10));

        $mesh->connectTriangles(0, 1);
        $mesh->connectTriangles(1, 2);

        $start = new Vec3(3, 0, 2);  // in triangle 0
        $goal = new Vec3(25, 0, 3);  // in triangle 2

        $path = $mesh->findPath($start, $goal);
        $this->assertNotNull($path);
        $this->assertGreaterThanOrEqual(2, count($path));

        // first and last should be start and goal
        $this->assertEqualsWithDelta(3.0, $path[0]->x, 0.001);
        $this->assertEqualsWithDelta(25.0, $path[count($path) - 1]->x, 0.001);
    }

    public function testNoPathDisconnected(): void
    {
        $mesh = new NavMesh();
        // two separate triangles, not connected
        $mesh->addTriangle(new Vec3(0, 0, 0), new Vec3(10, 0, 0), new Vec3(5, 0, 10));
        $mesh->addTriangle(new Vec3(100, 0, 0), new Vec3(110, 0, 0), new Vec3(105, 0, 10));

        $start = new Vec3(3, 0, 2);
        $goal = new Vec3(105, 0, 3);

        $this->assertNull($mesh->findPath($start, $goal));
    }

    public function testNoPathOutsideMesh(): void
    {
        $mesh = new NavMesh();
        $mesh->addTriangle(new Vec3(0, 0, 0), new Vec3(10, 0, 0), new Vec3(5, 0, 10));

        $start = new Vec3(-50, 0, -50);
        $goal = new Vec3(3, 0, 2);

        $this->assertNull($mesh->findPath($start, $goal));
    }
}
