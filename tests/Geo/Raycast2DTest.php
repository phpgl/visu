<?php

namespace VISU\Tests\Geo;

use PHPUnit\Framework\TestCase;
use VISU\Component\BoxCollider2D;
use VISU\Component\CircleCollider2D;
use VISU\ECS\EntityRegistry;
use VISU\Geo\Raycast2D;
use VISU\Geo\Transform;
use GL\Math\Vec3;

class Raycast2DTest extends TestCase
{
    private EntityRegistry $entities;

    protected function setUp(): void
    {
        $this->entities = new EntityRegistry();
        $this->entities->registerComponent(Transform::class);
        $this->entities->registerComponent(BoxCollider2D::class);
        $this->entities->registerComponent(CircleCollider2D::class);
    }

    private function createBoxEntity(float $x, float $y, float $hw = 16.0, float $hh = 16.0): int
    {
        $e = $this->entities->create();
        $t = new Transform();
        $t->position = new Vec3($x, $y, 0);
        $this->entities->attach($e, $t);

        $box = new BoxCollider2D();
        $box->halfWidth = $hw;
        $box->halfHeight = $hh;
        $this->entities->attach($e, $box);
        return $e;
    }

    private function createCircleEntity(float $x, float $y, float $radius = 16.0): int
    {
        $e = $this->entities->create();
        $t = new Transform();
        $t->position = new Vec3($x, $y, 0);
        $this->entities->attach($e, $t);

        $circle = new CircleCollider2D();
        $circle->radius = $radius;
        $this->entities->attach($e, $circle);
        return $e;
    }

    public function testPointQueryBox(): void
    {
        $e = $this->createBoxEntity(50, 50, 20, 20);

        $hits = Raycast2D::pointQuery($this->entities, 55, 55);
        $this->assertContains($e, $hits);

        $misses = Raycast2D::pointQuery($this->entities, 200, 200);
        $this->assertEmpty($misses);
    }

    public function testPointQueryCircle(): void
    {
        $e = $this->createCircleEntity(50, 50, 20);

        $hits = Raycast2D::pointQuery($this->entities, 55, 50);
        $this->assertContains($e, $hits);

        $misses = Raycast2D::pointQuery($this->entities, 100, 100);
        $this->assertEmpty($misses);
    }

    public function testPointQueryMultiple(): void
    {
        $e1 = $this->createBoxEntity(0, 0, 50, 50);
        $e2 = $this->createCircleEntity(0, 0, 30);

        $hits = Raycast2D::pointQuery($this->entities, 5, 5);
        $this->assertContains($e1, $hits);
        $this->assertContains($e2, $hits);
        $this->assertCount(2, $hits);
    }

    public function testRaycastHitsBox(): void
    {
        $e = $this->createBoxEntity(100, 0, 20, 20);

        $result = Raycast2D::cast($this->entities, 0, 0, 1, 0);
        $this->assertNotNull($result);
        $this->assertSame($e, $result->entityId);
        $this->assertEqualsWithDelta(80.0, $result->distance, 0.1);
        $this->assertEqualsWithDelta(80.0, $result->hitX, 0.1);
    }

    public function testRaycastHitsCircle(): void
    {
        $e = $this->createCircleEntity(100, 0, 20);

        $result = Raycast2D::cast($this->entities, 0, 0, 1, 0);
        $this->assertNotNull($result);
        $this->assertSame($e, $result->entityId);
        $this->assertEqualsWithDelta(80.0, $result->distance, 0.1);
    }

    public function testRaycastMiss(): void
    {
        $this->createBoxEntity(100, 100, 10, 10);

        // Ray going right, box is at (100,100) — miss
        $result = Raycast2D::cast($this->entities, 0, 0, 1, 0);
        $this->assertNull($result);
    }

    public function testRaycastClosest(): void
    {
        $e1 = $this->createBoxEntity(50, 0, 10, 10); // closer
        $e2 = $this->createBoxEntity(150, 0, 10, 10); // farther

        $result = Raycast2D::cast($this->entities, 0, 0, 1, 0);
        $this->assertNotNull($result);
        $this->assertSame($e1, $result->entityId);
    }

    public function testRaycastMaxDistance(): void
    {
        $this->createBoxEntity(200, 0, 10, 10);

        $result = Raycast2D::cast($this->entities, 0, 0, 1, 0, 100.0);
        $this->assertNull($result); // Beyond max distance
    }

    public function testPointQueryLayerFilter(): void
    {
        $e = $this->createBoxEntity(0, 0, 20, 20);
        $box = $this->entities->get($e, BoxCollider2D::class);
        $box->layer = 4;

        // Query with mask that doesn't include layer 4
        $hits = Raycast2D::pointQuery($this->entities, 5, 5, 2);
        $this->assertEmpty($hits);

        // Query with mask that includes layer 4
        $hits = Raycast2D::pointQuery($this->entities, 5, 5, 4);
        $this->assertContains($e, $hits);
    }
}
