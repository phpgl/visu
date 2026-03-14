<?php

namespace Tests\Geo;

use GL\Math\Vec3;
use PHPUnit\Framework\TestCase;
use VISU\Geo\Raycast3D;

class Raycast3DTest extends TestCase
{
    public function testRaySphereHit(): void
    {
        $origin = new Vec3(0.0, 0.0, -5.0);
        $dir = new Vec3(0.0, 0.0, 1.0);
        $center = new Vec3(0.0, 0.0, 0.0);

        $t = Raycast3D::raySphereIntersect($origin, $dir, $center, 1.0);
        $this->assertNotNull($t);
        $this->assertEqualsWithDelta(4.0, $t, 0.001);
    }

    public function testRaySphereMiss(): void
    {
        $origin = new Vec3(0.0, 5.0, -5.0);
        $dir = new Vec3(0.0, 0.0, 1.0);
        $center = new Vec3(0.0, 0.0, 0.0);

        $t = Raycast3D::raySphereIntersect($origin, $dir, $center, 1.0);
        $this->assertNull($t);
    }

    public function testRaySphereInsideHit(): void
    {
        // origin inside sphere
        $origin = new Vec3(0.0, 0.0, 0.0);
        $dir = new Vec3(0.0, 0.0, 1.0);
        $center = new Vec3(0.0, 0.0, 0.0);

        $t = Raycast3D::raySphereIntersect($origin, $dir, $center, 2.0);
        $this->assertNotNull($t);
        $this->assertEqualsWithDelta(2.0, $t, 0.001);
    }

    public function testRayCapsuleHitCylinder(): void
    {
        $origin = new Vec3(-5.0, 0.0, 0.0);
        $dir = new Vec3(1.0, 0.0, 0.0);
        $center = new Vec3(0.0, 0.0, 0.0);

        $t = Raycast3D::rayCapsuleIntersect($origin, $dir, $center, 1.0, 0.5);
        $this->assertNotNull($t);
        $this->assertEqualsWithDelta(4.5, $t, 0.001);
    }

    public function testRayCapsuleHitTopCap(): void
    {
        // shoot down at top hemisphere
        $origin = new Vec3(0.0, 5.0, 0.0);
        $dir = new Vec3(0.0, -1.0, 0.0);
        $center = new Vec3(0.0, 0.0, 0.0);
        $halfHeight = 1.0;
        $radius = 0.5;

        $t = Raycast3D::rayCapsuleIntersect($origin, $dir, $center, $halfHeight, $radius);
        $this->assertNotNull($t);
        // top of capsule is at y = halfHeight + radius = 1.5
        $this->assertEqualsWithDelta(3.5, $t, 0.001);
    }

    public function testRayCapsuleMiss(): void
    {
        $origin = new Vec3(-5.0, 10.0, 0.0);
        $dir = new Vec3(1.0, 0.0, 0.0);
        $center = new Vec3(0.0, 0.0, 0.0);

        $t = Raycast3D::rayCapsuleIntersect($origin, $dir, $center, 1.0, 0.5);
        $this->assertNull($t);
    }

    public function testRaySphereZeroDirection(): void
    {
        $origin = new Vec3(0.0, 0.0, 0.0);
        $dir = new Vec3(0.0, 0.0, 0.0);
        $center = new Vec3(1.0, 0.0, 0.0);

        $t = Raycast3D::raySphereIntersect($origin, $dir, $center, 0.5);
        $this->assertNull($t);
    }

    public function testRaySphereBehind(): void
    {
        // sphere is behind the ray
        $origin = new Vec3(0.0, 0.0, 5.0);
        $dir = new Vec3(0.0, 0.0, 1.0);
        $center = new Vec3(0.0, 0.0, 0.0);

        $t = Raycast3D::raySphereIntersect($origin, $dir, $center, 1.0);
        $this->assertNull($t);
    }
}
