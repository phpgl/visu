<?php

namespace VISU\FlyUI;

use GL\VectorGraphics\VGColor;

class FUICard extends FUILayout
{
    public VGColor $backgroundColor;

    public float $borderRadius;

    public ?VGColor $borderColor;

    public float $borderWidth;

    /**
     * Constructs a new view
     */
    public function __construct()
    {
        parent::__construct(FlyUI::$instance->theme->cardPadding);

        $this->backgroundColor = FlyUI::$instance->theme->cardBackgroundColor;
        $this->borderRadius = FlyUI::$instance->theme->cardBorderRadius;
        $this->borderColor = FlyUI::$instance->theme->cardBorderColor;
        $this->borderWidth = FlyUI::$instance->theme->cardBorderWidth;
        $this->spacingY = FlyUI::$instance->theme->cardSpacing;
    }

    /**
     * Renders the current view using the provided context
     */
    public function renderContent(FUIRenderContext $ctx) : void
    {
        $finalPos = $ctx->origin;
        $finalSize = $ctx->containerSize;

        // borders are always drawn inset in FlyUI, as VG draws them in the middle
        // we have to adjust the position and size of the rectangle
        if ($this->borderColor) {
            $finalPos->x = $finalPos->x + $this->borderWidth * 0.5;
            $finalPos->y = $finalPos->y + $this->borderWidth * 0.5;
            $finalSize->x = $finalSize->x - $this->borderWidth;
            $finalSize->y = $finalSize->y - $this->borderWidth;
        }

        $ctx->vg->beginPath();
        $ctx->vg->fillColor($this->backgroundColor);
        $ctx->vg->roundedRect(
            $finalPos->x,
            $finalPos->y,
            $finalSize->x,
            $finalSize->y,
            $this->borderRadius
        );
        $ctx->vg->fill();

        if ($this->borderColor) {
            $ctx->vg->strokeColor($this->borderColor);
            $ctx->vg->strokeWidth($this->borderWidth);
            $ctx->vg->stroke();
        }

        // pass to children
        parent::renderContent($ctx);
    }
}