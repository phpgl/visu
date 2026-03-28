<?php

namespace VISU\Component;

use GL\Math\Vec3;

class SphereCollider3D
{
    /**
     * Radius of the sphere collider.
     */
    public float $radius;

    /**
     * Offset from the entity's transform position.
     */
    public Vec3 $offset;

    /**
     * If true, this collider generates TriggerSignal instead of CollisionSignal.
     */
    public bool $isTrigger = false;

    /**
     * Collision layer for filtering (bitmask).
     */
    public int $layer = 1;

    /**
     * Which layers this collider collides with (bitmask).
     */
    public int $mask = 0xFFFF;

    public function __construct(
        float $radius = 0.5,
        ?Vec3 $offset = null,
    ) {
        $this->radius = $radius;
        $this->offset = $offset ?? new Vec3(0.0, 0.0, 0.0);
    }
}
