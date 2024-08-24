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
     * Boolean flag to indicate if the view is being hovered
     */
    public bool $isHovered = false;

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
    public function getEstimatedHeight(Vec2 $containerSize) : float
    {
        $height = 0.0;
        foreach($this->children as $child) {
            $height += $child->getEstimatedHeight($containerSize);
        }
        
        return $height + $this->padding->y * 2;
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

// class FUIView
// {
//     /**
//      * Vertical sizing mode
//      * 
//      * Should the view be sized to fit its content or fill the available space
//      */
//     public FUIViewSizing $sizingVertical = FUIViewSizing::fit;

//     /**
//      * An array of child views
//      * 
//      * @var array<FUIView>
//      */
//     public array $children = [];

//     /**
//      * Constructs a new view
//      */
//     public function __construct(
//         /**
//          * Margin is represented as a Vec4 where:
//          *  x = left
//          *  y = top
//          *  z = right
//          *  w = bottom
//          */
//         public ?Vec4 $margin = null,

//         /**
//          * Padding is represented as a Vec2
//          *  x = horizontal padding
//          *  y = vertical padding
//          */
//         public ?Vec2 $padding = null,
//         public ?Vec2 $size = null,
//     )
//     {
//         $this->margin = $margin ?? new Vec4(0);
//         $this->padding = $padding ?? new Vec2(0);
//         $this->size = $size ?? new Vec2(0);
//     }

//     /**
//      * Sets the views margin
//      * Margin is the space around the view
//      */
//     public function setMargin(float $marginLeft, float $marginTop, float $marginRight, float $marginBottom) : self
//     {
//         $this->margin = new Vec4($marginLeft, $marginTop, $marginRight, $marginBottom);
//         return $this;
//     }

//     /**
//      * Sets the horizontal and vertical margin
//      */
//     public function setMarginXY(float $horizontal, float $vertical) : self
//     {
//         $this->margin->x = $horizontal;
//         $this->margin->z = $horizontal;
//         $this->margin->y = $vertical;
//         $this->margin->w = $vertical;
//         return $this;
//     }

//     /**
//      * Sets the views X horizontal margin
//      */
//     public function setMarginX(float $marginX) : self
//     {
//         $this->margin->x = $marginX;
//         $this->margin->z = $marginX;
//         return $this;
//     }

//     /**
//      * Sets the views Y vertical margin
//      */
//     public function setMarginY(float $marginY) : self
//     {
//         $this->margin->y = $marginY;
//         $this->margin->w = $marginY;
//         return $this;
//     }

//     /**
//      * Sets the views padding
//      * Padding is the space inside the view to its content
//      */
//     public function setPaddingV(Vec2 $padding) : self
//     {
//         $this->padding = $padding;
//         return $this;
//     }

//     /**
//      * Sets the views padding
//      * Padding is the space inside the view to its content
//      */
//     public function setPadding(float $paddingX, float $paddingY) : self
//     {
//         $this->padding = new Vec2($paddingX, $paddingY);
//         return $this;
//     }

//     /**
//      * Sets the views X padding
//      * Padding is the space inside the view to its content
//      */
//     public function setPaddingX(float $paddingX) : self
//     {
//         $this->padding->x = $paddingX;
//         return $this;
//     }

//     /**
//      * Sets the views Y padding
//      * Padding is the space inside the view to its content
//      */
//     public function setPaddingY(float $paddingY) : self
//     {
//         $this->padding->y = $paddingY;
//         return $this;
//     }

//     /**
//      * Sets a fixed height value for the view
//      * Note, this will override the sizing mode
//      */
//     public function setFixedHeight(float $height) : self
//     {
//         $this->size->y = $height;
//         $this->sizingVertical = FUIViewSizing::fixed;
//         return $this;
//     }

//     /**
//      * Sets a factor height value for the view
//      * Note, this will override the sizing mode
//      */
//     public function setFactorHeight(float $factor) : self
//     {
//         $this->size->y = $factor;
//         $this->sizingVertical = FUIViewSizing::factor;
//         return $this;
//     }

//     /**
//      * Sets the vertical sizing mode
//      */
//     public function setVerticalSizing(FUIViewSizing $sizing) : self
//     {
//         $this->sizingVertical = $sizing;
//         return $this;
//     }

//     /**
//      * Returns the height of the current view and its children
//      * 
//      * Note: This is used for layouting in some sizing modes
//      */
//     public function getEstimatedHeight() : float
//     {
//         $height = 0.0;
//         foreach($this->children as $child) {
//             $height += $child->getEstimatedHeight();
//         }
        
//         return $height + $this->padding->y * 2;
//     }

//     /**
//      * Renders the current view using the provided context
//      */
//     public function render(FUIRenderContext $ctx) : float
//     {
//         $initalOrigin = $ctx->origin->copy();
//         $initalSize = $ctx->containerSize->copy();

//         // first apply the margin to the context
//         $ctx->origin = $ctx->origin + new Vec2($this->margin->x, $this->margin->y);

//         if ($this->sizingVertical === FUIViewSizing::fit) {
//             $ctx->containerSize->y = $this->getEstimatedHeight();
//         } elseif ($this->sizingVertical === FUIViewSizing::fixed) {
//             $ctx->containerSize->y = $this->size->y;
//         } elseif ($this->sizingVertical === FUIViewSizing::factor) {
//             $ctx->containerSize->y = $ctx->containerSize->y * $this->size->y;
//         }

//         // margin is is applied after the sizing
//         // we reduce the container size by top and bottom margin
//         $ctx->containerSize->y = $ctx->containerSize->y - $this->margin->y - $this->margin->w;
//         $ctx->containerSize->x = $ctx->containerSize->x - $this->margin->x - $this->margin->z;

//         // calculate the effective height of the entire view
//         $effectiveHeight = ($ctx->origin->y + $ctx->containerSize->y + $this->margin->w) - $initalOrigin->y;

//         // START debug
//         $cursorPos = $ctx->input->getCursorPosition();
//         if (1 || $cursorPos->x >= $ctx->origin->x && $cursorPos->x <= $ctx->origin->x + $ctx->containerSize->x &&
//             $cursorPos->y >= $ctx->origin->y && $cursorPos->y <= $ctx->origin->y + $ctx->containerSize->y
//         ) {
//             $ctx->vg->beginPath();
//             $ctx->vg->rect($ctx->origin->x, $ctx->origin->y, $ctx->containerSize->x, $ctx->containerSize->y);
//             $ctx->vg->strokeColor(VGColor::red());
//             $ctx->vg->stroke();
//             $ctx->vg->fillColor(VGColor::red());
//             $ctx->vg->fontSize(12);
//             $ctx->vg->text($ctx->origin->x + 15, $ctx->origin->y + 15, 'origin(' . $ctx->origin->x . ', ' . $ctx->origin->y . '), size(' . $ctx->containerSize->x . ', ' . $ctx->containerSize->y . '), offset(' . $ctx->verticalOffset . '), height(' . $effectiveHeight . ')');
//         }
//         // END debug

//         // apply padding to the context
//         $ctx->origin = $ctx->origin + $this->padding;
//         $ctx->containerSize = $ctx->containerSize - ($this->padding * 2);

//         // render the children
//         foreach($this->children as $child) {
//             $ctx->origin->y = $ctx->origin->y + $child->render($ctx);
//         }

//         // update the origin for the next view
//         $ctx->origin = $initalOrigin;
//         $ctx->containerSize = $initalSize;

//         return $effectiveHeight;
//     }
// }