<?php

namespace VISU\Component;

class BoxCollider2D
{
    /**
     * Half-extents (width/2, height/2) of the box collider.
     */
    public float $halfWidth = 16.0;
    public float $halfHeight = 16.0;

    /**
     * Offset from the entity's transform position.
     */
    public float $offsetX = 0.0;
    public float $offsetY = 0.0;

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
}
