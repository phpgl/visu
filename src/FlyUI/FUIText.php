<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGAlign;
use GL\VectorGraphics\VGColor;

class FUIText extends FUIView
{
    /**
     * Constructs a new view
     */
    public function __construct(
        public string $text,
        public ?VGColor $color = null,
        public float $fontSize = 16,
    )
    {
        parent::__construct(new Vec2(0, 0));
    }

    /**
     * Returns the height of the current view and its children
     * This is used for layouting purposes
     */
    public function getEstimatedHeight() : float
    {
        return $this->fontSize + $this->padding->y * 2;
    }

    /**
     * Renders the current view using the provided context
     */
    public function render(FUIRenderContext $ctx) : void
    {
        $finalPos = $ctx->origin;
        $finalSize = $ctx->containerSize;

        if (!$this->color) {
            $ctx->vg->fillColor(VGColor::black());
        } else {
            $ctx->vg->fillColor($this->color);
        }

        $ctx->vg->textAlign(VGAlign::LEFT | VGAlign::TOP);
        $ctx->vg->fontSize($this->fontSize);
        $ctx->vg->text($finalPos->x, $finalPos->y, $this->text);

        parent::render($ctx);
    }
}