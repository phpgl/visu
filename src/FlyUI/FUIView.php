<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\Math\Vec4;
use GL\VectorGraphics\VGColor;

class FUIView
{
    /**
     * An array of child views
     * 
     * @var array<FUIView>
     */
    public array $children = [];

    /**
     * Padding is represented as a Vec2
     *  x = horizontal padding
     *  y = vertical padding
     */
    public Vec2 $padding;

    /**
     * Constructs a new view
     */
    public function __construct(
        /**
         * Padding is represented as a Vec2
         *  x = horizontal padding
         *  y = vertical padding
         */
        ?Vec2 $padding = null,
    )
    {
        $this->padding = $padding ?? new Vec2(0);
    }

    /**
     * Sets the views padding
     * Padding is the space inside the view to its content
     */
    public function padding(float $horizontal, float $vertical) : self
    {
        $this->padding = new Vec2($horizontal, $vertical);
        return $this;
    }

    /**
     * Sets the views X padding
     * Padding is the space inside the view to its content
     */
    public function paddingX(float $paddingX) : self
    {
        $this->padding->x = $paddingX;
        return $this;
    }

    /**
     * Sets the views Y padding
     * Padding is the space inside the view to its content
     */
    public function paddingY(float $paddingY) : self
    {
        $this->padding->y = $paddingY;
        return $this;
    }

    /**
     * Returns the height of the current view and its children
     * 
     * Note: This is used for layouting in some sizing modes
     */
    public function getEstimatedHeight(FUIRenderContext $ctx) : float
    {
        $height = 0.0;
        foreach($this->children as $child) {
            $height += $child->getEstimatedHeight($ctx);
        }
        
        return $height + $this->padding->y * 2;
    }

    /**
     * Returns the width of the current view and its children
     * 
     * Note: This is used for layouting in some sizing modes
     */
    public function getEstimatedWidth(FUIRenderContext $ctx) : float
    {
        $width = 0.0;
        foreach($this->children as $child) {
            $width += $child->getEstimatedWidth($ctx);
        }
        
        return $width + $this->padding->x * 2;
    }
 
    /**
     * Renders the current view using the provided context
     */
    public function render(FUIRenderContext $ctx) : float
    {
        $initalOrigin = $ctx->origin->copy();
        $initalSize = $ctx->containerSize->copy();

        // apply padding to the context
        $ctx->origin = $ctx->origin + $this->padding;
        $ctx->containerSize = $ctx->containerSize - ($this->padding * 2);

        // render the children
        foreach($this->children as $child) {
            $ctx->origin->y = $ctx->origin->y + $child->render($ctx);
        }

        // update the origin for the next view
        $ctx->origin = $initalOrigin;
        $ctx->containerSize = $initalSize;

        return $ctx->containerSize->y;
    }
}