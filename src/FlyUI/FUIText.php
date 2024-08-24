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
        parent::__construct();
    }

    /**
     * Returns the height of the current view and its children
     * This is used for layouting purposes
     */
    public function getEstimatedHeight(Vec2 $containerSize) : float
    {
        return $this->fontSize + $this->padding->y * 2;
    }

    /**
     * Renders the current view using the provided context
     */
    public function render(FUIRenderContext $ctx) : float
    {
        $height = $this->getEstimatedHeight($ctx->containerSize);

        if (!$this->color) {
            $ctx->vg->fillColor(VGColor::black());
        } else {
            $ctx->vg->fillColor($this->color);
        }

        // // render green background
        // $ctx->vg->beginPath();
        // $ctx->vg->fillColor(VGColor::green());
        // $ctx->vg->rect($ctx->origin->x, $ctx->origin->y, $ctx->containerSize->x, $height - 1);
        // $ctx->vg->fill();

        $ctx->vg->textAlign(VGAlign::LEFT | VGAlign::TOP);
        $ctx->vg->fontSize($this->fontSize);
        $ctx->vg->fillColor(VGColor::black());
        $ctx->vg->text($ctx->origin->x + $this->padding->x, $ctx->origin->y + $this->padding->y, $this->text);

        // no pass to parent, as this is a leaf element
        return $height;
    }
}