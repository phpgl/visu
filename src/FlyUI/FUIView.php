<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\Math\Vec4;

abstract class FUIView
{
    /**
     * An array of child views
     * 
     * @var array<FUIView>
     */
    public array $children = [];

    /**
     * Padding is represented as a Vec4
     *  x = left padding
     *  y = right padding
     *  z = top padding
     *  w = bottom padding
     */
    public Vec4 $padding;

    /**
     * Constructs a new view
     */
    public function __construct(
        /**
         * Padding is represented as a Vec4
         *  x = left padding
         *  y = right padding
         *  z = top padding
         *  w = bottom padding
         */
        ?Vec4 $padding = null,
    )
    {
        $this->padding = $padding ?? new Vec4(0, 0, 0, 0);
    }

    /**
     * Sets the views padding
     * Padding is the space inside the view to its content
     */
    public function padding(float $horizontal, float $vertical) : self
    {
        $this->padding = new Vec4($horizontal, $horizontal, $vertical, $vertical);
        return $this;
    }

    /**
     * Sets the views padding with individual values
     * Padding is the space inside the view to its content
     */
    public function paddingFull(float $left, float $right, float $top, float $bottom) : self
    {
        $this->padding = new Vec4($left, $right, $top, $bottom);
        return $this;
    }

    /**
     * Sets all padding values to the same value
     * Padding is the space inside the view to its content
     */
    public function paddingAll(float $padding) : self
    {
        $this->padding = new Vec4($padding, $padding, $padding, $padding);
        return $this;
    }

    /**
     * Sets the views left padding
     * Padding is the space inside the view to its content
     */
    public function paddingLeft(float $paddingLeft) : self
    {
        $this->padding->x = $paddingLeft;
        return $this;
    }

    /**
     * Sets the views right padding
     * Padding is the space inside the view to its content
     */
    public function paddingRight(float $paddingRight) : self
    {
        $this->padding->y = $paddingRight;
        return $this;
    }

    /**
     * Sets the views top padding
     * Padding is the space inside the view to its content
     */
    public function paddingTop(float $paddingTop) : self
    {
        $this->padding->z = $paddingTop;
        return $this;
    }

    /**
     * Sets the views bottom padding
     * Padding is the space inside the view to its content
     */
    public function paddingBottom(float $paddingBottom) : self
    {
        $this->padding->w = $paddingBottom;
        return $this;
    }

    /**
     * Sets the views horizontal padding (left and right)
     * Padding is the space inside the view to its content
     */
    public function paddingX(float $paddingX) : self
    {
        $this->padding->x = $paddingX;
        $this->padding->y = $paddingX;
        return $this;
    }

    /**
     * Sets the views vertical padding (top and bottom)
     * Padding is the space inside the view to its content
     */
    public function paddingY(float $paddingY) : self
    {
        $this->padding->z = $paddingY;
        $this->padding->w = $paddingY;
        return $this;
    }

    /**
     * Returns the height of the current view and its children
     * 
     * Note: This is used for layouting in some sizing modes
     */
    abstract public function getEstimatedSize(FUIRenderContext $ctx) : Vec2;
 
    /**
     * Renders the current view using the provided context
     */
    abstract public function render(FUIRenderContext $ctx) : void;
}