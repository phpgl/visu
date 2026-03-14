<?php

namespace VISU\Tests\System;

use PHPUnit\Framework\TestCase;
use VISU\ECS\EntityRegistry;
use VISU\Geo\Transform;
use VISU\System\Camera2DSystem;
use GL\Math\Vec3;

class Camera2DSystemTest extends TestCase
{
    private EntityRegistry $entities;
    private Camera2DSystem $camera;

    protected function setUp(): void
    {
        $this->entities = new EntityRegistry();
        $this->entities->registerComponent(Transform::class);
        $this->camera = new Camera2DSystem();
        $this->camera->register($this->entities);
    }

    public function testInitialPosition(): void
    {
        $this->assertEqualsWithDelta(0.0, $this->camera->cameraData->x, 0.001);
        $this->assertEqualsWithDelta(0.0, $this->camera->cameraData->y, 0.001);
        $this->assertEqualsWithDelta(1.0, $this->camera->cameraData->zoom, 0.001);
    }

    public function testSetPosition(): void
    {
        $this->camera->setPosition(100.0, 200.0);
        $this->assertEqualsWithDelta(100.0, $this->camera->cameraData->x, 0.001);
        $this->assertEqualsWithDelta(200.0, $this->camera->cameraData->y, 0.001);
    }

    public function testSetZoom(): void
    {
        $this->camera->setZoom(2.5);
        $this->assertEqualsWithDelta(2.5, $this->camera->cameraData->zoom, 0.001);
    }

    public function testZoomLimits(): void
    {
        $this->camera->setZoomLimits(0.5, 3.0);
        $this->camera->setZoom(0.1);
        $this->assertEqualsWithDelta(0.5, $this->camera->cameraData->zoom, 0.001);

        $this->camera->setZoom(5.0);
        $this->assertEqualsWithDelta(3.0, $this->camera->cameraData->zoom, 0.001);
    }

    public function testZoomDelta(): void
    {
        $this->camera->setZoom(1.0);
        $this->camera->zoom(0.5);
        $this->assertEqualsWithDelta(1.5, $this->camera->cameraData->zoom, 0.001);
    }

    public function testBoundsClamp(): void
    {
        $this->camera->setBounds(-100, -100, 100, 100);
        $this->camera->setPosition(200, 200);
        $this->assertEqualsWithDelta(100.0, $this->camera->cameraData->x, 0.001);
        $this->assertEqualsWithDelta(100.0, $this->camera->cameraData->y, 0.001);

        $this->camera->setPosition(-200, -200);
        $this->assertEqualsWithDelta(-100.0, $this->camera->cameraData->x, 0.001);
        $this->assertEqualsWithDelta(-100.0, $this->camera->cameraData->y, 0.001);
    }

    public function testFollowTarget(): void
    {
        $e = $this->entities->create();
        $t = new Transform();
        $t->position = new Vec3(100, 50, 0);
        $this->entities->attach($e, $t);

        $this->camera->setFollowTarget($e, 0.0); // 0 damping = instant follow

        $this->camera->update($this->entities);
        $this->assertEqualsWithDelta(100.0, $this->camera->cameraData->x, 1.0);
        $this->assertEqualsWithDelta(50.0, $this->camera->cameraData->y, 1.0);
    }

    public function testFollowTargetWithDamping(): void
    {
        $e = $this->entities->create();
        $t = new Transform();
        $t->position = new Vec3(100, 0, 0);
        $this->entities->attach($e, $t);

        $this->camera->setFollowTarget($e, 0.5); // 50% damping

        $this->camera->update($this->entities);
        // With damping 0.5, t = 0.5, moves halfway: 0 + (100-0)*0.5 = 50
        $this->assertEqualsWithDelta(50.0, $this->camera->cameraData->x, 1.0);
    }

    public function testShakeStartsAndStops(): void
    {
        $this->assertFalse($this->camera->isShaking());

        $this->camera->shake(0.5, 0.3);
        $this->assertTrue($this->camera->isShaking());

        // Run enough frames to exhaust shake duration (0.3s at ~60fps = ~18 frames)
        for ($i = 0; $i < 30; $i++) {
            $this->camera->update($this->entities);
        }

        $this->assertFalse($this->camera->isShaking());
    }

    public function testShakeMovesCamera(): void
    {
        $this->camera->setPosition(100, 100);
        $this->camera->shake(1.0, 1.0, 20.0);

        // Run a few frames and check the camera moved
        $positions = [];
        for ($i = 0; $i < 5; $i++) {
            $this->camera->update($this->entities);
            $positions[] = [$this->camera->cameraData->x, $this->camera->cameraData->y];
        }

        // At least one position should differ from the base (100, 100)
        $moved = false;
        foreach ($positions as [$x, $y]) {
            if (abs($x - 100.0) > 0.001 || abs($y - 100.0) > 0.001) {
                $moved = true;
                break;
            }
        }
        $this->assertTrue($moved, 'Camera should move during shake');
    }

    public function testShakeResetsAfterDuration(): void
    {
        $this->camera->setPosition(50, 50);
        $this->camera->shake(1.0, 0.1);

        // Exhaust shake
        for ($i = 0; $i < 20; $i++) {
            $this->camera->update($this->entities);
        }

        // After shake ends, camera should return close to base position
        $this->assertEqualsWithDelta(50.0, $this->camera->cameraData->x, 0.1);
        $this->assertEqualsWithDelta(50.0, $this->camera->cameraData->y, 0.1);
    }

    public function testUpdateWithoutFollowTarget(): void
    {
        $this->camera->setPosition(42, 24);
        $this->camera->update($this->entities);
        $this->assertEqualsWithDelta(42.0, $this->camera->cameraData->x, 0.001);
        $this->assertEqualsWithDelta(24.0, $this->camera->cameraData->y, 0.001);
    }
}
