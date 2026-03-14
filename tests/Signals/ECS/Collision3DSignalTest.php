<?php

namespace Tests\Signals\ECS;

use GL\Math\Vec3;
use PHPUnit\Framework\TestCase;
use VISU\Signals\ECS\Collision3DSignal;

class Collision3DSignalTest extends TestCase
{
    public function testProperties(): void
    {
        $contact = new Vec3(1.0, 2.0, 3.0);
        $normal = new Vec3(0.0, 1.0, 0.0);
        $signal = new Collision3DSignal(10, 20, $contact, $normal, 0.5);

        $this->assertSame(10, $signal->entityA);
        $this->assertSame(20, $signal->entityB);
        $this->assertEqualsWithDelta(1.0, $signal->contactPoint->x, 0.001);
        $this->assertEqualsWithDelta(1.0, $signal->contactNormal->y, 0.001);
        $this->assertSame(0.5, $signal->penetration);
    }
}
