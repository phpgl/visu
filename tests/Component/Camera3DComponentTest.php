<?php

namespace Tests\Component;

use GL\Math\Vec3;
use PHPUnit\Framework\TestCase;
use VISU\Component\Camera3DComponent;

class Camera3DComponentTest extends TestCase
{
    public function testDefaults(): void
    {
        $comp = new Camera3DComponent();

        $this->assertSame(0.0, $comp->yaw);
        $this->assertSame(-15.0, $comp->pitch);
        $this->assertSame(-89.0, $comp->pitchMin);
        $this->assertSame(89.0, $comp->pitchMax);
        $this->assertSame(0.15, $comp->sensitivity);
        $this->assertSame(0.3, $comp->moveSpeed);
        $this->assertSame(3.0, $comp->sprintMultiplier);
    }

    public function testOrbitDefaults(): void
    {
        $comp = new Camera3DComponent();

        $this->assertSame(10.0, $comp->orbitDistance);
        $this->assertSame(1.0, $comp->orbitDistanceMin);
        $this->assertSame(100.0, $comp->orbitDistanceMax);
        $this->assertSame(1.0, $comp->orbitZoomSpeed);
        $this->assertSame(0.01, $comp->orbitPanSpeed);
        $this->assertInstanceOf(Vec3::class, $comp->orbitTarget);
    }

    public function testThirdPersonDefaults(): void
    {
        $comp = new Camera3DComponent();

        $this->assertSame(0, $comp->followTarget);
        $this->assertSame(1.5, $comp->followHeightOffset);
        $this->assertSame(5.0, $comp->followDistance);
        $this->assertSame(1.5, $comp->followDistanceMin);
        $this->assertSame(20.0, $comp->followDistanceMax);
        $this->assertSame(0.1, $comp->followDamping);
    }

    public function testPitchClamping(): void
    {
        $comp = new Camera3DComponent();

        // simulate pitch clamping logic as it would happen in the system
        $comp->pitch = 100.0;
        $clamped = max($comp->pitchMin, min($comp->pitchMax, $comp->pitch));
        $this->assertSame(89.0, $clamped);

        $comp->pitch = -100.0;
        $clamped = max($comp->pitchMin, min($comp->pitchMax, $comp->pitch));
        $this->assertSame(-89.0, $clamped);
    }

    public function testOrbitDistanceClamping(): void
    {
        $comp = new Camera3DComponent();

        $distance = 200.0;
        $clamped = max($comp->orbitDistanceMin, min($comp->orbitDistanceMax, $distance));
        $this->assertSame(100.0, $clamped);

        $distance = 0.1;
        $clamped = max($comp->orbitDistanceMin, min($comp->orbitDistanceMax, $distance));
        $this->assertSame(1.0, $clamped);
    }

    public function testCustomOrbitTarget(): void
    {
        $comp = new Camera3DComponent();
        $comp->orbitTarget = new Vec3(5.0, 2.0, -3.0);

        $this->assertEqualsWithDelta(5.0, $comp->orbitTarget->x, 0.001);
        $this->assertEqualsWithDelta(2.0, $comp->orbitTarget->y, 0.001);
        $this->assertEqualsWithDelta(-3.0, $comp->orbitTarget->z, 0.001);
    }
}
