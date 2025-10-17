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

        // pass to children
        parent::renderContent($ctx);

        if ($this->borderColor) {
            $ctx->vg->strokeColor($this->borderColor);
            $ctx->vg->strokeWidth($this->borderWidth);
            $ctx->vg->stroke();
        }
    }
}