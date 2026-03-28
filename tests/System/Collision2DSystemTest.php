<?php

namespace VISU\Tests\System;

use PHPUnit\Framework\TestCase;
use VISU\Component\BoxCollider2D;
use VISU\Component\CircleCollider2D;
use VISU\ECS\EntityRegistry;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Signal\Dispatcher;
use VISU\Signals\ECS\CollisionSignal;
use VISU\Signals\ECS\TriggerSignal;
use VISU\System\Collision2DSystem;
use GL\Math\Vec3;

class Collision2DSystemTest extends TestCase
{
    private EntityRegistry $entities;
    private Dispatcher $dispatcher;
    private Collision2DSystem $system;

    protected function setUp(): void
    {
        $this->entities = new EntityRegistry();
        $this->entities->registerComponent(Transform::class);
        $this->dispatcher = new Dispatcher();
        $this->system = new Collision2DSystem($this->dispatcher);
        $this->system->register($this->entities);
    }

    private function createBoxEntity(float $x, float $y, float $hw = 16.0, float $hh = 16.0, bool $isTrigger = false): int
    {
        $e = $this->entities->create();
        $t = new Transform();
        $t->position = new Vec3($x, $y, 0);
        $this->entities->attach($e, $t);

        $box = new BoxCollider2D();
        $box->halfWidth = $hw;
        $box->halfHeight = $hh;
        $box->isTrigger = $isTrigger;
        $this->entities->attach($e, $box);
        return $e;
    }

    private function createCircleEntity(float $x, float $y, float $radius = 16.0, bool $isTrigger = false): int
    {
        $e = $this->entities->create();
        $t = new Transform();
        $t->position = new Vec3($x, $y, 0);
        $this->entities->attach($e, $t);

        $circle = new CircleCollider2D();
        $circle->radius = $radius;
        $circle->isTrigger = $isTrigger;
        $this->entities->attach($e, $circle);
        return $e;
    }

    public function testBoxBoxCollision(): void
    {
        $this->createBoxEntity(0, 0, 16, 16);
        $this->createBoxEntity(20, 0, 16, 16); // overlapping (distance=20, combined hw=32)

        $collisions = [];
        $this->dispatcher->register('collision', function (CollisionSignal $s) use (&$collisions) {
            $collisions[] = $s;
        });

        $this->system->update($this->entities);
        $this->assertCount(1, $collisions);
    }

    public function testBoxBoxNoCollision(): void
    {
        $this->createBoxEntity(0, 0, 16, 16);
        $this->createBoxEntity(100, 0, 16, 16); // far apart

        $collisions = [];
        $this->dispatcher->register('collision', function (CollisionSignal $s) use (&$collisions) {
            $collisions[] = $s;
        });

        $this->system->update($this->entities);
        $this->assertCount(0, $collisions);
    }

    public function testCircleCircleCollision(): void
    {
        $this->createCircleEntity(0, 0, 20);
        $this->createCircleEntity(30, 0, 20); // overlapping (distance=30, combined r=40)

        $collisions = [];
        $this->dispatcher->register('collision', function (CollisionSignal $s) use (&$collisions) {
            $collisions[] = $s;
        });

        $this->system->update($this->entities);
        $this->assertCount(1, $collisions);
    }

    public function testCircleCircleNoCollision(): void
    {
        $this->createCircleEntity(0, 0, 10);
        $this->createCircleEntity(30, 0, 10); // not overlapping (distance=30, combined r=20)

        $collisions = [];
        $this->dispatcher->register('collision', function (CollisionSignal $s) use (&$collisions) {
            $collisions[] = $s;
        });

        $this->system->update($this->entities);
        $this->assertCount(0, $collisions);
    }

    public function testBoxCircleCollision(): void
    {
        $this->createBoxEntity(0, 0, 16, 16);
        $this->createCircleEntity(20, 0, 10); // overlapping

        $collisions = [];
        $this->dispatcher->register('collision', function (CollisionSignal $s) use (&$collisions) {
            $collisions[] = $s;
        });

        $this->system->update($this->entities);
        $this->assertCount(1, $collisions);
    }

    public function testTriggerSignal(): void
    {
        $this->createBoxEntity(0, 0, 16, 16, true); // trigger
        $this->createBoxEntity(10, 0, 16, 16);

        $triggers = [];
        $this->dispatcher->register('collision.trigger', function (TriggerSignal $s) use (&$triggers) {
            $triggers[] = $s;
        });

        // First frame: ENTER
        $this->system->update($this->entities);
        $this->assertCount(1, $triggers);
        $this->assertSame(TriggerSignal::ENTER, $triggers[0]->phase);

        // Second frame: STAY
        $triggers = [];
        $this->system->update($this->entities);
        $this->assertCount(1, $triggers);
        $this->assertSame(TriggerSignal::STAY, $triggers[0]->phase);
    }

    public function testTriggerExit(): void
    {
        $eA = $this->createBoxEntity(0, 0, 16, 16, true);
        $eB = $this->createBoxEntity(10, 0, 16, 16);

        $triggers = [];
        $this->dispatcher->register('collision.trigger', function (TriggerSignal $s) use (&$triggers) {
            $triggers[] = $s;
        });

        // Frame 1: ENTER
        $this->system->update($this->entities);
        $this->assertSame(TriggerSignal::ENTER, $triggers[0]->phase);

        // Move B far away
        $tB = $this->entities->get($eB, Transform::class);
        $tB->position = new Vec3(200, 0, 0);

        // Frame 2: EXIT
        $triggers = [];
        $this->system->update($this->entities);
        $this->assertCount(1, $triggers);
        $this->assertSame(TriggerSignal::EXIT, $triggers[0]->phase);
    }

    public function testLayerFiltering(): void
    {
        $eA = $this->createBoxEntity(0, 0, 16, 16);
        $eB = $this->createBoxEntity(10, 0, 16, 16);

        // Set different layers
        $boxA = $this->entities->get($eA, BoxCollider2D::class);
        $boxA->layer = 1;
        $boxA->mask = 2; // only collides with layer 2

        $boxB = $this->entities->get($eB, BoxCollider2D::class);
        $boxB->layer = 4; // not in mask of A
        $boxB->mask = 1;

        $collisions = [];
        $this->dispatcher->register('collision', function (CollisionSignal $s) use (&$collisions) {
            $collisions[] = $s;
        });

        $this->system->update($this->entities);
        $this->assertCount(0, $collisions); // filtered by layer
    }

    public function testContactPoint(): void
    {
        $this->createBoxEntity(0, 0, 16, 16);
        $this->createBoxEntity(20, 0, 16, 16);

        $collisions = [];
        $this->dispatcher->register('collision', function (CollisionSignal $s) use (&$collisions) {
            $collisions[] = $s;
        });

        $this->system->update($this->entities);
        $this->assertCount(1, $collisions);
        // Contact point should be midpoint between centers
        $this->assertEqualsWithDelta(10.0, $collisions[0]->contactX, 0.1);
        $this->assertEqualsWithDelta(0.0, $collisions[0]->contactY, 0.1);
    }
}
