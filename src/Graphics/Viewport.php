<?php 

namespace VISU\Graphics;

use GL\Math\Vec2;
use VISU\Geo\AABB;
use VISU\Geo\AABB2D;

class Viewport
{
    /**
     * Viewport width
     */
    public readonly float $width;

    /**
     * Viewport height
     */
    public readonly float $height;

    /**
     * AABB of the viewport
     */
    public readonly AABB2D $aabb;

    /**
     * Constructs a new viewport
     */
    public function __construct(
        public readonly float $left,
        public readonly float $right,
        public readonly float $bottom,
        public readonly float $top,
        public readonly float $screenSpaceWidth,
        public readonly float $screenSpaceHeight,
    )
    {
        $this->width = $this->right - $this->left;
        $this->height = $this->bottom - $this->top;

        $this->aabb = new AABB2D(
            new Vec2(min($this->left, $this->right), min($this->bottom, $this->top)), 
            new Vec2(max($this->left, $this->right), max($this->bottom, $this->top))
        );
    }

    /**
     * Returns the absolute width of the viewport, the "width" can be negative
     * If your viewport is flipped, you can use this method to get the absolute width
     */
    public function getWidth() : float
    {
        return abs($this->width);
    }

    /**
     * Returns the absolute height of the viewport, the "height" can be negative
     * If your viewport is flipped, you can use this method to get the absolute height
     */
    public function getHeight() : float
    {
        return abs($this->height);
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
            $this->left + $this->width / 2,
            $this->bottom + $this->height / 2,
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
            $this->left + $this->width / 2,
            $this->bottom,
        );
    }

    /**
     * Returns the top center of the viewport
     */
    public function getTopCenter() : Vec2
    {
        return new Vec2(
            $this->left + $this->width / 2,
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
            $this->bottom + $this->height / 2,
        );
    }

    /**
     * Returns the right center of the viewport
     */
    public function getRightCenter() : Vec2
    {
        return new Vec2(
            $this->right,
            $this->bottom + $this->height / 2,
        );
    }

    /**
     * Returns boolean indicating if the given point is inside the viewport
     */
    public function contains(Vec2 $point) : bool
    {
        return $this->aabb->contains($point);
    }

    /**
     * Returns boolean indicating if the given AABB is inside the viewport
     */
    public function containsAABB(AABB2D $aabb) : bool
    {
        return $this->aabb->containsAABB($aabb);
    }

    /**
     * Converts the given screen space point to viewport space
     */
    public function screenSpaceToViewSpace(Vec2 $point) : Vec2
    {
        return new Vec2(
            $this->left + $point->x * $this->width / $this->screenSpaceWidth,
            $this->top + $point->y * $this->height / $this->screenSpaceHeight,
        );
    }

    /**
     * Converts the given viewport space point to screen space
     */
    public function viewSpaceToScreenSpace(Vec2 $point) : Vec2
    {
        return new Vec2(
            ($point->x - $this->left) * $this->screenSpaceWidth / $this->width,
            ($this->top - $point->y) * $this->screenSpaceHeight / $this->height,
        );
    }
}