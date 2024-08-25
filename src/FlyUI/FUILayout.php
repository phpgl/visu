<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGColor;

enum FUILayoutSizing 
{
    /**
     * Fill the available space
     */
    case fill;

    /**
     * Size to fit the content
     */
    case fit;

    /**
     * Fixed size in effective pixels
     */
    case fixed;

    /**
     * Size relative to the parent
     */
    case factor;
}


class FUILayout extends FUIView
{
    /**
     * Vertical sizing mode
     * 
     * Should the view be sized to fit its content or fill the available space
     */
    private FUILayoutSizing $sizingVertical = FUILayoutSizing::fit;

    /**
     * Horizontal sizing mode
     * 
     * Should the view be sized to fit its content or fill the available space
     */
    private FUILayoutSizing $sizingHorizontal = FUILayoutSizing::fill;

    /**
     * The layouts width
     * This can represent a:
     *  - `fixed` value in effective pixels
     *  - `factor` of the parent or the available space
     */
    public ?float $width = null;

    /**
     * The layouts height
     * This can represent a:
     *  - `fixed` value in effective pixels
     *  - `factor` of the parent or the available space
     */
    public ?float $height = null;

    /**
     * Left margin in effective pixels
     * When set to `null` the margin is to be considered in "auto" mode.
     */
    public ?float $left = null;

    /**
     * Top margin in effective pixels
     * When set to `null` the margin is to be considered in "auto" mode.
     */
    public ?float $top = null;

    /**
     * Right margin in effective pixels
     * When set to `null` the margin is to be considered in "auto" mode.
     */
    public ?float $right = null;

    /**
     * Bottom margin in effective pixels
     * When set to `null` the margin is to be considered in "auto" mode.
     */
    public ?float $bottom = null;

    /**
     * A vertical gab between the children 
     */
    public float $spacingY = 0.0;

    /**
     * Sets the horizontal and vertical margins
     */
    public function marginXY(float $horizontal, float $vertical) : self
    {
        $this->left = $horizontal;
        $this->right = $horizontal;
        $this->top = $vertical;
        $this->bottom = $vertical;
        return $this;
    }

    /**
     * Sets the horizontal margin
     */
    public function marginX(float $horizontal) : self
    {
        $this->left = $horizontal;
        $this->right = $horizontal;
        return $this;
    }

    /**
     * Sets the vertical margin
     */
    public function marginY(float $vertical) : self
    {
        $this->top = $vertical;
        $this->bottom = $vertical;
        return $this;
    }

    /**
     * Sets the left margin
     */
    public function marginLeft(float $left) : self
    {
        $this->left = $left;
        return $this;
    }

    /**
     * Sets the right margin
     */
    public function marginRight(float $right) : self
    {
        $this->right = $right;
        return $this;
    }

    /**
     * Sets the top margin
     */
    public function marginTop(float $top) : self
    {
        $this->top = $top;
        return $this;
    }

    /**
     * Sets the bottom margin
     */
    public function marginBottom(float $bottom) : self
    {
        $this->bottom = $bottom;
        return $this;
    }

    /**
     * Sets the a fixed height for the layout
     * Note: This will override the vertical sizing mode
     */
    public function fixedHeight(float $height) : self
    {
        $this->height = $height;
        $this->sizingVertical = FUILayoutSizing::fixed;
        return $this;
    }

    /**
     * Sets the a fixed width for the layout
     * Note: This will override the horizontal sizing mode
     */
    public function fixedWidth(float $width) : self
    {
        $this->width = $width;
        $this->sizingHorizontal = FUILayoutSizing::fixed;
        return $this;
    }

    /**
     * Sets the a factor height for the layout
     * Note: This will override the vertical sizing mode
     */
    public function factorHeight(float $factor) : self
    {
        $this->height = $factor;
        $this->sizingVertical = FUILayoutSizing::factor;
        return $this;
    }

    /**
     * Sets the a factor width for the layout
     * Note: This will override the horizontal sizing mode
     */
    public function factorWidth(float $factor) : self
    {
        $this->width = $factor;
        $this->sizingHorizontal = FUILayoutSizing::factor;
        return $this;
    }

    /**
     * Sets the vertical sizing mode
     */
    public function verticalSizing(FUILayoutSizing $sizing) : self
    {
        $this->sizingVertical = $sizing;
        return $this;
    }

    /**
     * Sets the horizontal sizing mode to `fill`
     */
    public function verticalFill() : self
    {
        $this->sizingVertical = FUILayoutSizing::fill;
        return $this;
    }

    /**
     * Sets the horizontal sizing mode to `fit`
     */
    public function verticalFit() : self
    {
        $this->sizingVertical = FUILayoutSizing::fit;
        return $this;
    }

    /**
     * Returns the height of the content aka the sum of all children
     */
    private function getContentHeight(FUIRenderContext $ctx) : float
    {
        $height = 0.0;

        // height of all children
        if (count($this->children) !== 0) {
            foreach($this->children as $child) {
                $height += $child->getEstimatedHeight($ctx) + $this->spacingY;
            }
    
            $height -= $this->spacingY; // remove the last spacing
        }
        
        // add the padding to the height
        $height += $this->padding->y * 2;
        
        return $height;
    }

    /**
     * Returns the height of the current view and its children
     * 
     * Note: This is used for layouting in some sizing modes
     */
    public function getEstimatedHeight(FUIRenderContext $ctx) : float
    {
        if ($this->sizingVertical === FUILayoutSizing::fixed) {
            return $this->height + $this->top + $this->bottom;
        }

        elseif ($this->sizingVertical === FUILayoutSizing::factor) {
            return $ctx->containerSize->y * $this->height;
        }
        
        elseif ($this->sizingVertical === FUILayoutSizing::fill) {
            return $ctx->containerSize->y;
        }

        $height = $this->getContentHeight($ctx);

        // add the margin to the height
        $height += $this->top + $this->bottom;
        
        return $height;
    }

    protected function renderContent(FUIRenderContext $ctx) : void
    {
        // // START debug
        // $cursorPos = $ctx->input->getCursorPosition();
        // if ($cursorPos->x >= $ctx->origin->x && $cursorPos->x <= $ctx->origin->x + $ctx->containerSize->x &&
        //     $cursorPos->y >= $ctx->origin->y && $cursorPos->y <= $ctx->origin->y + $ctx->containerSize->y
        // ) {
        //     $ctx->vg->beginPath();
        //     $ctx->vg->rect($ctx->origin->x, $ctx->origin->y, $ctx->containerSize->x, $ctx->containerSize->y);
        //     $ctx->vg->strokeColor(VGColor::red());
        //     $ctx->vg->stroke();
        //     $ctx->vg->fillColor(VGColor::red());
        //     $ctx->vg->fontSize(12);
        //     $ctx->vg->text($ctx->origin->x + 15, $ctx->origin->y + 15, 'origin(' . $ctx->origin->x . ', ' . $ctx->origin->y . '), size(' . $ctx->containerSize->x . ', ' . $ctx->containerSize->y . ')');
        // }
        // // END debug

        // apply padding to the context
        $ctx->origin = $ctx->origin + $this->padding;
        $ctx->containerSize = $ctx->containerSize - ($this->padding * 2);
        $linestart = $ctx->origin->x;
        
        // render the children
        foreach($this->children as $child) {
            $ctx->origin->y = $ctx->origin->y + $child->render($ctx) + $this->spacingY;
            $ctx->origin->x = $linestart;
        }
    }

    /**
     * Renders the current view using the provided context
     */
    public function render(FUIRenderContext $ctx) : float
    {
        $initalOrigin = $ctx->origin->copy();
        $initalSize = $ctx->containerSize->copy();

        // my head hurts from trying to cleanly implement this, 
        // honestly after rewriting this over and over again for like 2 hours
        // im sick of it and im going to make it simple and stupid
        // somebody smarter then me is very welcome to refactor this
        if ($this->width === null && $this->height === null) 
        {
            // no width and no height means we only consider the margins
            // this is only possible in fit and fill mode
            $ctx->origin = $ctx->origin + new Vec2($this->left ?? 0, $this->top ?? 0);

            if ($this->sizingVertical === FUILayoutSizing::fit) {
                $ctx->containerSize->y = $this->getContentHeight($ctx);
            } elseif ($this->sizingVertical === FUILayoutSizing::fill) {
                // reduce the container size by the margin
                $ctx->containerSize->y = $ctx->containerSize->y - ($this->top ?? 0) - ($this->bottom ?? 0);
            } else {
                throw new FUIException(sprintf('The vertical sizing mode %s is not supported for layouts without a width or height value.', $this->sizingVertical));
            }

            if ($this->sizingHorizontal === FUILayoutSizing::fill) {
                // reduce the container size by the margin
                $ctx->containerSize->x = $ctx->containerSize->x - ($this->left ?? 0) - ($this->right ?? 0);
            } else {
                throw new FUIException(sprintf('The horizontal sizing mode %s is not supported for layouts without a width or height value.', $this->sizingHorizontal));
            }

        }
        else
        {
            // we have a height value
            if ($this->height !== null)
            {
                $ctx->origin->y = $ctx->origin->y + ($this->top ?? 0);

                if ($this->sizingVertical === FUILayoutSizing::fixed) {
                    $ctx->containerSize->y = $this->height;
                } elseif ($this->sizingVertical === FUILayoutSizing::factor) {
                    $ctx->containerSize->y = $ctx->containerSize->y * $this->height;
                }

                $ctx->containerSize->y = $ctx->containerSize->y - ($this->top ?? 0) - ($this->bottom ?? 0);
            } 
            else
            {
                $ctx->origin->y = $ctx->origin->y + ($this->top ?? 0);

                // no height value, we will use the content height
                if ($this->sizingVertical === FUILayoutSizing::fit) {
                    $ctx->containerSize->y = $this->getContentHeight($ctx);
                } elseif ($this->sizingVertical === FUILayoutSizing::fill) {
                    $ctx->containerSize->y = $ctx->containerSize->y - ($this->top ?? 0) - ($this->bottom ?? 0);
                } else {
                    throw new FUIException(sprintf('The sizing mode %s is not supported for layouts without a width or height value.', $this->sizingVertical));
                }
            } 

            // we have a width value
            if ($this->width !== null)
            {
                $fullWidth = $ctx->containerSize->x;

                if ($this->sizingHorizontal === FUILayoutSizing::fixed) {
                    $ctx->containerSize->x = $this->width;
                } elseif ($this->sizingHorizontal === FUILayoutSizing::factor) {
                    $ctx->containerSize->x = $ctx->containerSize->x * $this->width;
                }

                // because we have a fixed width, right and left cannot be set at the same time
                // we will prioritize the left margin 
                if ($this->left !== null) {
                    $ctx->origin->x = $ctx->origin->x + $this->left;
                }
                else if ($this->right !== null) {
                    $ctx->origin->x = $ctx->origin->x + $fullWidth - $ctx->containerSize->x - $this->right;
                }

                $ctx->containerSize->x = $ctx->containerSize->x - ($this->left ?? 0) - ($this->right ?? 0);
            }
            else 
            {
                $ctx->origin->x = $ctx->origin->x + ($this->left ?? 0);

                if ($this->sizingHorizontal === FUILayoutSizing::fill) {
                    $ctx->containerSize->x = $ctx->containerSize->x - ($this->left ?? 0) - ($this->right ?? 0);
                } else {
                    throw new FUIException(sprintf('The sizing mode %s is not supported for layouts without a width or height value.', $this->sizingHorizontal));
                }
            }
        }

        // render the content
        $this->renderContent($ctx);

        // update origin and container for the next view in the layout
        $ctx->origin = $initalOrigin;
        $ctx->containerSize = $initalSize;

        return $this->getEstimatedHeight($ctx);
    }
}