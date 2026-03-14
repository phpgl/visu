<?php

namespace Tests\Component;

use PHPUnit\Framework\TestCase;
use VISU\Component\CapsuleCollider3D;

class CapsuleCollider3DTest extends TestCase
{
    public function testDefaults(): void
    {
        $capsule = new CapsuleCollider3D();
        $this->assertSame(0.3, $capsule->radius);
        $this->assertSame(0.5, $capsule->halfHeight);
        $this->assertEqualsWithDelta(0.0, $capsule->offset->x, 0.001);
        $this->assertFalse($capsule->isTrigger);
    }

    public function testCustomValues(): void
    {
        $capsule = new CapsuleCollider3D(0.5, 1.0);
        $this->assertSame(0.5, $capsule->radius);
        $this->assertSame(1.0, $capsule->halfHeight);
    }

    public function testTotalHeight(): void
    {
        $capsule = new CapsuleCollider3D(0.3, 0.5);
        // total height = 2*halfHeight + 2*radius = 1.0 + 0.6 = 1.6
        $totalHeight = 2.0 * $capsule->halfHeight + 2.0 * $capsule->radius;
        $this->assertEqualsWithDelta(1.6, $totalHeight, 0.001);
    }
}
