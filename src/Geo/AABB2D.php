<?php

namespace VISU\Geo;

use BadFunctionCallException;
use GL\Math\Vec2;

class AABB2D
{
    /**
     * Creates a union AABB from the given AABBs
     */
    public static function union(AABB2D ...$aabbs) : AABB2D
    {
        if (count($aabbs) < 2) {
            throw new BadFunctionCallException('You need to pass at least 2 AABBs to create a union AABB');
        }

        $union = array_shift($aabbs);
        $union = $union->copy();

        foreach ($aabbs as $aabb) {
            $union->extend($aabb);
        }

        return $union;
    }

    /**
     * Constructs an axis aligned bounding box
     * 
     * @param Vec2 $min 
     * @param Vec2 $max 
     */
    public function __construct(
        public Vec2 $min,
        public Vec2 $max
    )
    {
        
    }

    /**
     * Returns the width of the AABB
     */
    public function width() : float
    {
        return $this->max->x - $this->min->x;
    }

    /**
     * Returns the height of the AABB
     */
    public function height() : float
    {
        return $this->max->y - $this->min->y;
    }

    /**
     * Returns a copy of the current AABB
     */
    public function copy() : AABB2D
    {
        return new AABB2D(
            $this->min->copy(),
            $this->max->copy()
        );
    }

    /**
     * Returns the center of the AABB
     */
    public function getCenter() : float|Vec2
    {
        return ($this->min + $this->max) * 0.5;
    }

    /**
     * Extends the current AABB to include the given AABB
     */
    public function extend(AABB2D $aabb) : void
    {
        $this->min->x = min($this->min->x, $aabb->min->x);
        $this->min->y = min($this->min->y, $aabb->min->y);

        $this->max->x = max($this->max->x, $aabb->max->x);
        $this->max->y = max($this->max->y, $aabb->max->y);
    }

    /**
     * Returns true if the given AABB intersects with the current AABB
     */
    public function intersects(AABB2D $aabb) : bool
    {
        return !(
            $this->min->x > $aabb->max->x ||
            $this->max->x < $aabb->min->x ||
            $this->min->y > $aabb->max->y ||
            $this->max->y < $aabb->min->y
        );
    }

    /**
     * Returns true if the given point is inside the AABB
     */
    public function contains(Vec2 $point) : bool
    {
        return !(
            $point->x < $this->min->x ||
            $point->x > $this->max->x ||
            $point->y < $this->min->y ||
            $point->y > $this->max->y
        );
    }

    /**
     * Returns true if the given AABB is inside the current AABB
     */
    public function containsAABB(AABB2D $aabb) : bool
    {
        return !(
            $aabb->min->x < $this->min->x ||
            $aabb->max->x > $this->max->x ||
            $aabb->min->y < $this->min->y ||
            $aabb->max->y > $this->max->y
        );
    }

    /**
     * Applies the given transform to the AABB
     */
    public function applyTransform(Transform $transform) : void
    {
        $minScaled = $this->min * new Vec2($transform->scale->x, $transform->scale->y);
        $maxScaled = $this->max * new Vec2($transform->scale->x, $transform->scale->y);
    
        $this->min->x = min($minScaled->x, $maxScaled->x);
        $this->min->y = min($minScaled->y, $maxScaled->y);
        $this->max->x = max($minScaled->x, $maxScaled->x);
        $this->max->y = max($minScaled->y, $maxScaled->y);
    
        $this->min = $this->min + new Vec2($transform->position->x, $transform->position->y);
        $this->max = $this->max + new Vec2($transform->position->x, $transform->position->y);
    }
}
