<?php

namespace VISU\FlyUI;

use GL\VectorGraphics\VGColor;

class FUIBox extends FUILayout
{
    public VGColor $backgroundColor;

    /**
     * Constructs a new view
     */
    public function __construct(VGColor $backgroundColor)
    {
        parent::__construct();
        $this->backgroundColor = $backgroundColor;
        
        $this->verticalFill(); // by default a box fills the parent container
    }

    /**
     * Renders the current view using the provided context
     */
    public function renderContent(FUIRenderContext $ctx) : void
    {
        $finalPos = $ctx->origin;
        $finalSize = $ctx->containerSize;

        $ctx->vg->beginPath();
        $ctx->vg->fillColor($this->backgroundColor);
        $ctx->vg->rect(
            $finalPos->x,
            $finalPos->y,
            $finalSize->x,
            $finalSize->y,
        );
        $ctx->vg->fill();

        // pass to children
        parent::renderContent($ctx);
    }
}