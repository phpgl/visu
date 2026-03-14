<?php

namespace Tests\Component;

use GL\Math\Vec3;
use PHPUnit\Framework\TestCase;
use VISU\Component\RigidBody3D;

class RigidBody3DTest extends TestCase
{
    public function testDefaults(): void
    {
        $rb = new RigidBody3D();
        $this->assertSame(1.0, $rb->mass);
        $this->assertEqualsWithDelta(0.0, $rb->velocity->x, 0.001);
        $this->assertEqualsWithDelta(0.0, $rb->velocity->y, 0.001);
        $this->assertEqualsWithDelta(0.0, $rb->velocity->z, 0.001);
        $this->assertSame(1.0, $rb->gravityScale);
        $this->assertSame(0.3, $rb->restitution);
        $this->assertSame(0.5, $rb->friction);
        $this->assertFalse($rb->isKinematic);
    }

    public function testInverseMass(): void
    {
        $rb = new RigidBody3D(2.0);
        $this->assertEqualsWithDelta(0.5, $rb->inverseMass(), 0.001);
    }

    public function testInverseMassStatic(): void
    {
        $rb = new RigidBody3D(0.0);
        $this->assertSame(0.0, $rb->inverseMass());
    }

    public function testInverseMassKinematic(): void
    {
        $rb = new RigidBody3D(5.0);
        $rb->isKinematic = true;
        $this->assertSame(0.0, $rb->inverseMass());
    }

    public function testAddForce(): void
    {
        $rb = new RigidBody3D();
        $rb->addForce(new Vec3(10.0, 0.0, 0.0));
        $rb->addForce(new Vec3(0.0, 5.0, 0.0));
        $this->assertEqualsWithDelta(10.0, $rb->force->x, 0.001);
        $this->assertEqualsWithDelta(5.0, $rb->force->y, 0.001);
    }

    public function testAddImpulse(): void
    {
        $rb = new RigidBody3D(2.0);
        $rb->addImpulse(new Vec3(10.0, 0.0, 0.0));
        // impulse / mass = 10 / 2 = 5
        $this->assertEqualsWithDelta(5.0, $rb->velocity->x, 0.001);
    }

    public function testAddImpulseStatic(): void
    {
        $rb = new RigidBody3D(0.0);
        $rb->addImpulse(new Vec3(10.0, 0.0, 0.0));
        // static body shouldn't move
        $this->assertEqualsWithDelta(0.0, $rb->velocity->x, 0.001);
    }

    public function testFreezeConstraints(): void
    {
        $rb = new RigidBody3D();
        $rb->freezePositionX = true;
        $rb->freezePositionY = true;
        $rb->freezeRotationZ = true;
        $this->assertTrue($rb->freezePositionX);
        $this->assertTrue($rb->freezePositionY);
        $this->assertFalse($rb->freezePositionZ);
        $this->assertTrue($rb->freezeRotationZ);
    }
}
