<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;

class FUIView
{
    /**
     * An array of child views
     * 
     * @var array<FUIView>
     */
    public array $children = [];

    /**
     * Constructs a new view
     */
    public function __construct(
        public Vec2 $padding
    )
    {
    }

    /**
     * Returns the height of the current view and its children
     * This is used for layouting purposes
     */
    public function getEstimatedHeight() : float
    {
        $height = 0.0;
        foreach($this->children as $child) {
            $height += $child->getEstimatedHeight();
        }
        
        return $height + $this->padding->y * 2;
    }

    /**
     * Renders the current view using the provided context
     */
    public function render(FUIRenderContext $ctx) : void
    {
        $ctx->origin = $ctx->origin + $this->padding;
        $ctx->containerSize = $ctx->containerSize - ($this->padding * 2);
        foreach($this->children as $child) {
            $child->render($ctx);
            $ctx->origin->y = $ctx->origin->y + $child->getEstimatedHeight();
        }
    }
}