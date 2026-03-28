<?php

namespace Tests\Component;

use GL\Math\Vec3;
use PHPUnit\Framework\TestCase;
use VISU\Component\SphereCollider3D;

class SphereCollider3DTest extends TestCase
{
    public function testDefaults(): void
    {
        $sphere = new SphereCollider3D();
        $this->assertSame(0.5, $sphere->radius);
        $this->assertEqualsWithDelta(0.0, $sphere->offset->x, 0.001);
        $this->assertFalse($sphere->isTrigger);
        $this->assertSame(1, $sphere->layer);
        $this->assertSame(0xFFFF, $sphere->mask);
    }

    public function testCustomRadius(): void
    {
        $sphere = new SphereCollider3D(2.0, new Vec3(1.0, 0.0, 0.0));
        $this->assertSame(2.0, $sphere->radius);
        $this->assertEqualsWithDelta(1.0, $sphere->offset->x, 0.001);
    }
}
