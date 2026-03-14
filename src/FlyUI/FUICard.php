<?php

namespace VISU\FlyUI;

use GL\VectorGraphics\VGColor;

class FUICard extends FUILayout
{
    public ?VGColor $borderColor;

    public float $borderWidth;

    /**
     * Constructs a new view
     */
    public function __construct()
    {
        parent::__construct(FlyUI::$instance->theme->cardPadding->copy());

        $this->backgroundColor = FlyUI::$instance->theme->cardBackgroundColor;
        $this->cornerRadius = FlyUI::$instance->theme->cardBorderRadius;
        $this->borderColor = FlyUI::$instance->theme->cardBorderColor;
        $this->borderWidth = FlyUI::$instance->theme->cardBorderWidth;
        $this->spacing = FlyUI::$instance->theme->cardSpacing;
    }

    /**
     * Renders the current view using the provided context
     */
    public function renderContent(FUIRenderContext $ctx) : void
    {
        // capture border rect before children render (copy to avoid reference mutation)
        $borderX = $ctx->origin->x + $this->borderWidth * 0.5;
        $borderY = $ctx->origin->y + $this->borderWidth * 0.5;
        $borderW = $ctx->containerSize->x - $this->borderWidth;
        $borderH = $ctx->containerSize->y - $this->borderWidth;

        // pass to children
        parent::renderContent($ctx);

        // draw the border on top of children with its own path
        if ($this->borderColor) {
            $ctx->vg->beginPath();
            if ($this->cornerRadius > 0.0) {
                $ctx->vg->roundedRect($borderX, $borderY, $borderW, $borderH, $this->cornerRadius);
            } else {
                $ctx->vg->rect($borderX, $borderY, $borderW, $borderH);
            }
            $ctx->vg->strokeColor($this->borderColor);
            $ctx->vg->strokeWidth($this->borderWidth);
            $ctx->vg->stroke();
        }
    }
}