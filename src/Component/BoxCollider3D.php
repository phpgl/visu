<?php

namespace VISU\Component;

use GL\Math\Vec3;

class BoxCollider3D
{
    /**
     * Half-extents of the box collider in local space.
     */
    public Vec3 $halfExtents;

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
        ?Vec3 $halfExtents = null,
        ?Vec3 $offset = null,
    ) {
        $this->halfExtents = $halfExtents ?? new Vec3(0.5, 0.5, 0.5);
        $this->offset = $offset ?? new Vec3(0.0, 0.0, 0.0);
    }
}
