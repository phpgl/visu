<?php

namespace VISU\Component;

use GL\Math\Vec3;

class CapsuleCollider3D
{
    /**
     * Radius of the capsule's hemisphere caps.
     */
    public float $radius;

    /**
     * Half-height of the cylindrical segment (total height = 2*halfHeight + 2*radius).
     */
    public float $halfHeight;

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
        float $radius = 0.3,
        float $halfHeight = 0.5,
        ?Vec3 $offset = null,
    ) {
        $this->radius = $radius;
        $this->halfHeight = $halfHeight;
        $this->offset = $offset ?? new Vec3(0.0, 0.0, 0.0);
    }
}
