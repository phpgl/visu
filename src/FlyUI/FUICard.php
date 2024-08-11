<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGColor;

enum FUICardSizig {
    case Fill;
    case Fit;
}

class FUICard extends FUIView
{
    /**
     * Vertical sizing mode
     * 
     * Should the card be sized to fit its content or fill the available space
     */
    public FUICardSizig $sizingVertical = FUICardSizig::Fill;

    /**
     * Horizontal sizing mode
     * 
     * Should the card be sized to fit its content or fill the available space
     */
    public FUICardSizig $sizingHorizontal = FUICardSizig::Fill;

    /**
     * Constructs a new view
     */
    public function __construct(
        public VGColor $backgroundColor,
        public float $borderRadius,
        public ?VGColor $borderColor = null,
        public float $borderWidth = 1.0
    )
    {
        parent::__construct(new Vec2(0, 0));
    }

    /**
     * Renders the current view using the provided context
     */
    public function render(FUIRenderContext $ctx) : void
    {
        $finalPos = $ctx->origin;
        $finalSize = $ctx->containerSize;

        if ($this->sizingVertical === FUICardSizig::Fit) {
            $finalSize->y = min($this->getEstimatedHeight(), $finalSize->y);
        }

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

        parent::render($ctx);
    }
}