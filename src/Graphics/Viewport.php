<?php 

namespace VISU\Graphics;

use GL\Math\Vec2;
use VISU\Geo\AABB;

class Viewport
{
    /**
     * Constructs a new viewport
     */
    public function __construct(
        public readonly float $left,
        public readonly float $right,
        public readonly float $bottom,
        public readonly float $top,
    )
    {
    }

    /**
     * Returns the width of the viewport
     */
    public function getWidth() : float
    {
        return $this->right - $this->left;
    }

    /**
     * Returns the height of the viewport
     */
    public function getHeight() : float
    {
        return $this->top - $this->bottom;
    }

    /**
     * Returns the aspect ratio of the viewport
     */
    public function getAspectRatio() : float
    {
        return $this->getWidth() / $this->getHeight();
    }

    /**
     * Returns the center of the viewport
     */
    public function getCenter() : Vec2
    {
        return new Vec2(
            $this->left + $this->getWidth() / 2,
            $this->bottom + $this->getHeight() / 2,
        );
    }

    /**
     * Returns the top left corner of the viewport
     */
    public function getTopLeft() : Vec2
    {
        return new Vec2($this->left, $this->top);
    }

    /**
     * Returns the top right corner of the viewport
     */
    public function getTopRight() : Vec2
    {
        return new Vec2($this->right, $this->top);
    }

    /**
     * Returns the bottom left corner of the viewport
     */
    public function getBottomLeft() : Vec2
    {
        return new Vec2($this->left, $this->bottom);
    }

    /**
     * Returns the bottom right corner of the viewport
     */
    public function getBottomRight() : Vec2
    {
        return new Vec2($this->right, $this->bottom);
    }

    /**
     * Returns the bottom center of the viewport
     */
    public function getBottomCenter() : Vec2
    {
        return new Vec2(
            $this->left + $this->getWidth() / 2,
            $this->bottom,
        );
    }

    /**
     * Returns the top center of the viewport
     */
    public function getTopCenter() : Vec2
    {
        return new Vec2(
            $this->left + $this->getWidth() / 2,
            $this->top,
        );
    }

    /**
     * Returns the left center of the viewport
     */
    public function getLeftCenter() : Vec2
    {
        return new Vec2(
            $this->left,
            $this->bottom + $this->getHeight() / 2,
        );
    }

    /**
     * Returns the right center of the viewport
     */
    public function getRightCenter() : Vec2
    {
        return new Vec2(
            $this->right,
            $this->bottom + $this->getHeight() / 2,
        );
    }

    /**
     * Returns boolean indicating if the given point is inside the viewport
     */
    public function contains(Vec2 $point) : bool
    {
        return $point->x >= $this->left
            && $point->x <= $this->right
            && $point->y >= $this->bottom
            && $point->y <= $this->top;
    }

    /**
     * Returns boolean indicating if the given AABB is inside the viewport
     */
    public function containsAABB(AABB $aabb) : bool
    {
        return $aabb->min->x >= $this->left
            && $aabb->max->x <= $this->right
            && $aabb->min->y >= $this->bottom
            && $aabb->max->y <= $this->top;
    }

    /**
     * Returns boolean indicating if the given viewport is inside the viewport
     */
    public function containsViewport(Viewport $viewport) : bool
    {
        return $viewport->left >= $this->left
            && $viewport->right <= $this->right
            && $viewport->bottom >= $this->bottom
            && $viewport->top <= $this->top;
    }

    /**
     * Returns boolean indicating if the given AABB intersects the viewport
     */
    public function intersectsAABB(AABB $aabb) : bool
    {
        return $aabb->min->x <= $this->right
            && $aabb->max->x >= $this->left
            && $aabb->min->y <= $this->top
            && $aabb->max->y >= $this->bottom;
    }

    /**
     * Returns boolean indicating if the given viewport intersects the viewport
     */
    public function intersectsViewport(Viewport $viewport) : bool
    {
        return $viewport->left <= $this->right
            && $viewport->right >= $this->left
            && $viewport->bottom <= $this->top
            && $viewport->top >= $this->bottom;
    }
}