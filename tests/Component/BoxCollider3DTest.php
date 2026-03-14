<?php

namespace Tests\Component;

use GL\Math\Vec3;
use PHPUnit\Framework\TestCase;
use VISU\Component\BoxCollider3D;

class BoxCollider3DTest extends TestCase
{
    public function testDefaults(): void
    {
        $box = new BoxCollider3D();
        $this->assertEqualsWithDelta(0.5, $box->halfExtents->x, 0.001);
        $this->assertEqualsWithDelta(0.5, $box->halfExtents->y, 0.001);
        $this->assertEqualsWithDelta(0.5, $box->halfExtents->z, 0.001);
        $this->assertEqualsWithDelta(0.0, $box->offset->x, 0.001);
        $this->assertFalse($box->isTrigger);
        $this->assertSame(1, $box->layer);
        $this->assertSame(0xFFFF, $box->mask);
    }

    public function testCustomExtents(): void
    {
        $box = new BoxCollider3D(new Vec3(2.0, 3.0, 1.5), new Vec3(0.0, 1.0, 0.0));
        $this->assertEqualsWithDelta(2.0, $box->halfExtents->x, 0.001);
        $this->assertEqualsWithDelta(3.0, $box->halfExtents->y, 0.001);
        $this->assertEqualsWithDelta(1.5, $box->halfExtents->z, 0.001);
        $this->assertEqualsWithDelta(1.0, $box->offset->y, 0.001);
    }
}
