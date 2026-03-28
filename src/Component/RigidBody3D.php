<?php

namespace VISU\Component;

use GL\Math\Vec3;

class RigidBody3D
{
    /**
     * Mass in kg (0 = infinite mass / static body)
     */
    public float $mass;

    /**
     * Linear velocity (units per second)
     */
    public Vec3 $velocity;

    /**
     * Angular velocity (radians per second, axis-angle)
     */
    public Vec3 $angularVelocity;

    /**
     * Gravity scale (multiplied with world gravity, 0 = no gravity)
     */
    public float $gravityScale = 1.0;

    /**
     * Linear drag (velocity damping per second, 0 = no drag)
     */
    public float $linearDrag = 0.01;

    /**
     * Angular drag (angular velocity damping per second)
     */
    public float $angularDrag = 0.05;

    /**
     * Bounciness / coefficient of restitution (0 = no bounce, 1 = perfect bounce)
     */
    public float $restitution = 0.3;

    /**
     * Friction coefficient (0 = ice, 1 = rubber)
     */
    public float $friction = 0.5;

    /**
     * If true, body is not affected by physics forces but can push dynamic bodies.
     */
    public bool $isKinematic = false;

    /**
     * Freeze position on specific axes
     */
    public bool $freezePositionX = false;
    public bool $freezePositionY = false;
    public bool $freezePositionZ = false;

    /**
     * Freeze rotation on specific axes
     */
    public bool $freezeRotationX = false;
    public bool $freezeRotationY = false;
    public bool $freezeRotationZ = false;

    /**
     * Accumulated force for the current tick (reset after integration)
     */
    public Vec3 $force;

    public function __construct(float $mass = 1.0)
    {
        $this->mass = $mass;
        $this->velocity = new Vec3(0.0, 0.0, 0.0);
        $this->angularVelocity = new Vec3(0.0, 0.0, 0.0);
        $this->force = new Vec3(0.0, 0.0, 0.0);
    }

    /**
     * Returns the inverse mass (0 for static/kinematic bodies)
     */
    public function inverseMass(): float
    {
        if ($this->mass <= 0.0 || $this->isKinematic) {
            return 0.0;
        }
        return 1.0 / $this->mass;
    }

    /**
     * Adds a force (in Newtons) to be applied during the next integration step.
     */
    public function addForce(Vec3 $f): void
    {
        $force = $this->force;
        $force->x = $force->x + $f->x;
        $force->y = $force->y + $f->y;
        $force->z = $force->z + $f->z;
    }

    /**
     * Adds an instantaneous impulse (mass * velocity change).
     */
    public function addImpulse(Vec3 $impulse): void
    {
        $invMass = $this->inverseMass();
        if ($invMass <= 0.0) return;

        $vel = $this->velocity;
        $vel->x = $vel->x + $impulse->x * $invMass;
        $vel->y = $vel->y + $impulse->y * $invMass;
        $vel->z = $vel->z + $impulse->z * $invMass;
    }
}
