<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGColor;

class FUILayout extends FUIView
{
    /**
     * Vertical sizing mode
     * 
     * Should the view be sized to fit its content or fill the available space
     */
    public FUILayoutSizing $sizingVertical = FUILayoutSizing::fit;

    /**
     * Horizontal sizing mode
     * 
     * Should the view be sized to fit its content or fill the available space
     */
    public FUILayoutSizing $sizingHorizontal = FUILayoutSizing::fill;

    /**
     * The layouts flow direction for its children
     */
    public FUILayoutFlow $flow = FUILayoutFlow::vertical;

    /**
     * The layouts alignment of its children
     */
    public FUILayoutAlignment $alignment = FUILayoutAlignment::topLeft;

    /**
     * The layouts width
     * This can represent a:
     *  - `fixed` value in effective pixels
     */
    public ?float $width = null;

    /**
     * The layouts height
     * This can represent a:
     *  - `fixed` value in effective pixels
     */
    public ?float $height = null;

    /**
     * A vertical gap between the children
     */
    public float $spacing = 0.0;

    /**
     * Background color of the view
     */
    public ?VGColor $backgroundColor = null;

    /**
     * Border radius of the view
     */
    public float $cornerRadius = 0.0;

    /**
     * Sets the layout flow direction
     */
    public function flow(FUILayoutFlow $flow) : self
    {
        $this->flow = $flow;
        return $this;
    }

    /**
     * Sets the alignment of the view
     */
    public function align(FUILayoutAlignment $alignment) : self
    {
        $this->alignment = $alignment;
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
     * Sets the horizontal sizing mode
     */
    public function horizontalSizing(FUILayoutSizing $sizing) : self
    {
        $this->sizingHorizontal = $sizing;
        return $this;
    }

    /**
     * Sets the horizontal sizing mode to `fill`
     */
    public function horizontalFill() : self
    {
        $this->sizingHorizontal = FUILayoutSizing::fill;
        return $this;
    }

    /**
     * Sets the horizontal sizing mode to `fit`
     */
    public function horizontalFit() : self
    {
        $this->sizingHorizontal = FUILayoutSizing::fit;
        return $this;
    }

    /**
     * Sets the vertical spacing between the children
     */
    public function spacing(float $spacing) : self
    {
        $this->spacing = $spacing;
        return $this;
    }

    /**
     * Sets the background color of the view
     */
    public function backgroundColor(VGColor $color, ?float $cornerRadius = null) : self
    {
        $this->backgroundColor = $color;
        if ($cornerRadius !== null) {
            $this->cornerRadius = $cornerRadius;
        }
        return $this;
    }

    /**
     * Calculate the offset for alignment within available space
     */
    private function calculateAlignmentOffset(Vec2 $totalChildrenSize, Vec2 $availableSize) : Vec2
    {
        $offset = new Vec2(0.0, 0.0);
        
        // calculate horizontal offset
        switch ($this->alignment) {
            case FUILayoutAlignment::topCenter:
            case FUILayoutAlignment::center:
            case FUILayoutAlignment::bottomCenter:
                $offset->x = ($availableSize->x - $totalChildrenSize->x) / 2.0;
                break;
                
            case FUILayoutAlignment::topRight:
            case FUILayoutAlignment::centerRight:
            case FUILayoutAlignment::bottomRight:
                $offset->x = $availableSize->x - $totalChildrenSize->x;
                break;
                
            default:
                $offset->x = 0.0; // left alignment (default)
                break;
        }
        
        // calculate vertical offset
        switch ($this->alignment) {
            case FUILayoutAlignment::centerLeft:
            case FUILayoutAlignment::center:
            case FUILayoutAlignment::centerRight:
                $offset->y = ($availableSize->y - $totalChildrenSize->y) / 2.0;
                break;
                
            case FUILayoutAlignment::bottomLeft:
            case FUILayoutAlignment::bottomCenter:
            case FUILayoutAlignment::bottomRight:
                $offset->y = $availableSize->y - $totalChildrenSize->y;
                break;
                
            default:
                $offset->y = 0.0; // top alignment (default)
                break;
        }
        
        // ensure offsets are never negative
        $offset->x = max(0.0, $offset->x);
        $offset->y = max(0.0, $offset->y);
        
        return $offset;
    }

    /**
     * Calculate the sizes of all children based on their sizing modes
     * This implements Figma-style fill behavior where fill children share available space equally
     * 
     * @return array<Vec2>
     */
    private function calculateChildrenSizes(FUIRenderContext $ctx) : array
    {
        if (empty($this->children)) {
            return [];
        }

        $availableSize = $ctx->containerSize->copy();
        $childrenSizes = [];
        $fillChildren = [];
        $nonFillSize = new Vec2(0.0, 0.0);
        
        // pre-calculate all children sizes once to avoid redundant calls
        $estimatedSizes = [];
        foreach ($this->children as $index => $child) {
            $estimatedSizes[$index] = $child->getEstimatedSize($ctx);
        }
        
        // first pass: categorize children and calculate non-fill sizes
        foreach ($this->children as $index => $child) {
            $childSize = $estimatedSizes[$index]; // use pre-calculated size
            
            if (!($child instanceof FUILayout)) {
                // for non-layout children, use their estimated size directly
                $childrenSizes[$index] = $childSize;
                
                if ($this->flow === FUILayoutFlow::horizontal) {
                    $nonFillSize->x = $nonFillSize->x + $childSize->x;
                    $nonFillSize->y = max($nonFillSize->y, $childSize->y);
                } else {
                    $nonFillSize->y = $nonFillSize->y + $childSize->y;
                    $nonFillSize->x = max($nonFillSize->x, $childSize->x);
                }
                continue;
            }

            // for layout children, check their sizing modes
            $childLayout = $child;
            $isHorizontalFill = $childLayout->sizingHorizontal === FUILayoutSizing::fill;
            $isVerticalFill = $childLayout->sizingVertical === FUILayoutSizing::fill;

            // check if this child should fill in the flow direction
            $shouldFillInFlowDirection = ($this->flow === FUILayoutFlow::horizontal && $isHorizontalFill) ||
                                       ($this->flow === FUILayoutFlow::vertical && $isVerticalFill);
            
            if ($shouldFillInFlowDirection) {
                // this child wants to fill in the flow direction
                $fillChildren[] = $index;
                
                // store initial size (will be updated in second pass for flow dimension)
                $childrenSizes[$index] = $childSize;
                
                // add to non-fill size calculation (perpendicular to flow)
                if ($this->flow === FUILayoutFlow::horizontal) {
                    // for horizontal flow, we don't add to x (that's handled in second pass)
                    // but we do need to track the maximum height
                    $nonFillSize->y = max($nonFillSize->y, $childSize->y);
                } else {
                    // for vertical flow, we don't add to y (that's handled in second pass)
                    // but we do need to track the maximum width
                    $nonFillSize->x = max($nonFillSize->x, $childSize->x);
                }
            } else {
                // use pre-calculated size for non-fill child
                $childrenSizes[$index] = $childSize;
                
                if ($this->flow === FUILayoutFlow::horizontal) {
                    $nonFillSize->x = $nonFillSize->x + $childSize->x;
                    $nonFillSize->y = max($nonFillSize->y, $childSize->y);
                } else {
                    $nonFillSize->y = $nonFillSize->y + $childSize->y;
                    $nonFillSize->x = max($nonFillSize->x, $childSize->x);
                }
            }
        }

        // calculate spacing
        $totalSpacing = $this->spacing * max(0.0, count($this->children) - 1);
        
        // second pass: calculate sizes for fill children
        if (!empty($fillChildren)) {
            $fillChildrenCount = count($fillChildren);
            
            if ($this->flow === FUILayoutFlow::horizontal) {
                $remainingWidth = max(0.0, $availableSize->x - $nonFillSize->x - $totalSpacing);
                $fillWidth = $remainingWidth / $fillChildrenCount;
                
                foreach ($fillChildren as $index) {
                    $currentSize = $childrenSizes[$index];
                    // reuse existing Vec2 object by modifying in place
                    $currentSize->x = $fillWidth;
                }
            } else {
                $remainingHeight = max(0.0, $availableSize->y - $nonFillSize->y - $totalSpacing);
                $fillHeight = $remainingHeight / $fillChildrenCount;
                
                foreach ($fillChildren as $index) {
                    $currentSize = $childrenSizes[$index];
                    // reuse existing Vec2 object by modifying in place
                    $currentSize->y = $fillHeight;
                }
            }
        }

        // return all calculated sizes
        return array_values($childrenSizes);
    }

    /**
     * Returns the height of the current view and its children
     * 
     * Note: This is used for layouting in some sizing modes
     */
    public function getEstimatedSize(FUIRenderContext $ctx) : Vec2
    {
        // start calculating size
        $size = new Vec2(0.0, 0.0);

        // handle horizontal sizing
        if ($this->sizingHorizontal === FUILayoutSizing::fixed && $this->width !== null) {
            $size->x = $this->width;
        } elseif ($this->sizingHorizontal === FUILayoutSizing::fill) {
            $size->x = $ctx->containerSize->x;
        }

        // handle vertical sizing
        if ($this->sizingVertical === FUILayoutSizing::fixed && $this->height !== null) {
            $size->y = $this->height;
        } elseif ($this->sizingVertical === FUILayoutSizing::fill) {
            $size->y = $ctx->containerSize->y;
        }

        // for fit mode, calculate the size based on children directly
        if ($this->sizingHorizontal === FUILayoutSizing::fit || $this->sizingVertical === FUILayoutSizing::fit) {
            // pre-calculate all children sizes once to avoid redundant calls
            $childrenEstimatedSizes = [];
            foreach ($this->children as $child) {
                $childrenEstimatedSizes[] = $child->getEstimatedSize($ctx);
            }
            
            if ($this->sizingHorizontal === FUILayoutSizing::fit) {
                if ($this->flow === FUILayoutFlow::horizontal) {
                    $totalWidth = 0.0;
                    foreach ($childrenEstimatedSizes as $childSize) {
                        $totalWidth = $totalWidth + $childSize->x;
                    }
                    $size->x = $totalWidth + ($this->spacing * max(0, count($this->children) - 1)) + ($this->padding->x + $this->padding->y);
                } else {
                    $maxWidth = 0.0;
                    foreach ($childrenEstimatedSizes as $childSize) {
                        if ($childSize->x > $maxWidth) {
                            $maxWidth = $childSize->x;
                        }
                    }
                    $size->x = $maxWidth + ($this->padding->x + $this->padding->y);
                }
            }
            
            if ($this->sizingVertical === FUILayoutSizing::fit) {
                if ($this->flow === FUILayoutFlow::vertical) {
                    $totalHeight = 0.0;
                    foreach ($childrenEstimatedSizes as $childSize) {
                        $totalHeight = $totalHeight + $childSize->y;
                    }
                    $size->y = $totalHeight + ($this->spacing * max(0, count($this->children) - 1)) + ($this->padding->z + $this->padding->w);
                } else {
                    $maxHeight = 0.0;
                    foreach ($childrenEstimatedSizes as $childSize) {
                        if ($childSize->y > $maxHeight) {
                            $maxHeight = $childSize->y;
                        }
                    }
                    $size->y = $maxHeight + ($this->padding->z + $this->padding->w);
                }
            }
        }

        return $size;
    }

    protected function renderContent(FUIRenderContext $ctx) : void
    {
        // calculate all children sizes once using optimized method
        $childrenSizes = $this->calculateChildrenSizes($ctx);
        
        if (empty($childrenSizes)) {
            return;
        }

        // calculate total size of all children including spacing
        $totalChildrenSize = new Vec2(0.0, 0.0);
        $totalSpacing = $this->spacing * max(0, count($childrenSizes) - 1);
        
        if ($this->flow === FUILayoutFlow::horizontal) {
            foreach ($childrenSizes as $childSize) {
                $totalChildrenSize = new Vec2(
                    $totalChildrenSize->x + $childSize->x,
                    max($totalChildrenSize->y, $childSize->y)
                );
            }
            $totalChildrenSize = new Vec2($totalChildrenSize->x + $totalSpacing, $totalChildrenSize->y);
        } else {
            foreach ($childrenSizes as $childSize) {
                $totalChildrenSize = new Vec2(
                    max($totalChildrenSize->x, $childSize->x),
                    $totalChildrenSize->y + $childSize->y
                );
            }
            $totalChildrenSize = new Vec2($totalChildrenSize->x, $totalChildrenSize->y + $totalSpacing);
        }

        // calculate alignment offset
        $alignmentOffset = $this->calculateAlignmentOffset($totalChildrenSize, $ctx->containerSize);
        
        // start rendering from the aligned position
        $containerOrigin = $ctx->origin + $alignmentOffset;

        foreach($this->children as $index => $child) 
        {
            // use the pre-calculated size for this child (no fallback needed since calculateChildrenSizes handles all children)
            $childSize = $childrenSizes[$index];

            // update the context for the child with the calculated size
            $originalOrigin = $ctx->origin;
            $originalSize = $ctx->containerSize;

            $ctx->origin = $containerOrigin->copy();
            $ctx->containerSize = $childSize; // reuse existing Vec2 object

            $child->render($ctx);

            // restore the original context
            $ctx->origin = $originalOrigin;
            $ctx->containerSize = $originalSize;

            // move to the next position based on flow direction
            if ($this->flow === FUILayoutFlow::horizontal) 
            {
                $containerOrigin = new Vec2(
                    $containerOrigin->x + $childSize->x + $this->spacing,
                    $containerOrigin->y
                );
            } 
            else 
            {
                $containerOrigin = new Vec2(
                    $containerOrigin->x,
                    $containerOrigin->y + $childSize->y + $this->spacing
                );
            }
        }
    }

    /**
     * Renders the current view using the provided context
     */
    public function render(FUIRenderContext $ctx) : void
    {
        $initalOrigin = $ctx->origin;
        $initalSize = $ctx->containerSize;

        // draw the background if we have one
        if ($this->backgroundColor) {
            $ctx->vg->beginPath();
            $ctx->vg->fillColor($this->backgroundColor);
            if ($this->cornerRadius > 0.0) {
                $ctx->vg->roundedRect($ctx->origin->x, $ctx->origin->y, $ctx->containerSize->x, $ctx->containerSize->y, $this->cornerRadius);
            } else {
                $ctx->vg->rect($ctx->origin->x, $ctx->origin->y, $ctx->containerSize->x, $ctx->containerSize->y);
            }
            $ctx->vg->fill();
        }

        // apply padding to the context
        $paddedOrigin = new Vec2($ctx->origin->x + $this->padding->x, $ctx->origin->y + $this->padding->z);
        $paddedSize = new Vec2(
            $ctx->containerSize->x - ($this->padding->x + $this->padding->y),
            $ctx->containerSize->y - ($this->padding->z + $this->padding->w)
        );
        
        $ctx->origin = $paddedOrigin;
        $ctx->containerSize = $paddedSize;
        
        // render the children
        $this->renderContent($ctx);

        // restore context, as children might have modified it
        $ctx->origin = $initalOrigin;
        $ctx->containerSize = $initalSize;
    }
}