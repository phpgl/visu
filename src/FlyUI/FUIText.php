<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGAlign;
use GL\VectorGraphics\VGColor;

class FUIText extends FUIView
{
    /**
     * The font size of the text
     */
    public float $fontSize;

    /**
     * Should the text be bold
     */
    public bool $isBold = false;

    /**
     * Constructs a new view
     */
    public function __construct(
        public string $text,
        public ?VGColor $color = null,
    )
    {
        parent::__construct();

        $this->fontSize = FlyUI::$instance->theme->fontSize;
    }

    /**
     * Sets the font size of the text
     */
    public function fontSize(float $size) : self
    {
        $this->fontSize = $size;
        return $this;
    }

    /**
     * Sets the text to be bold
     */
    public function bold() : self
    {
        $this->isBold = true;
        return $this;
    }

    /**
     * Returns the height of the current view and its children
     * 
     * Note: This is used for layouting in some sizing modes
     */
    public function getEstimatedSize(FUIRenderContext $ctx) : Vec2
    {
        return new Vec2(0, $this->fontSize + $this->padding->y * 2);
    }

    /**
     * Renders the current view using the provided context
     */
    public function render(FUIRenderContext $ctx) : void
    {
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

        if ($this->isBold) {
            $ctx->ensureSemiBoldFontFace();
        } else {
            $ctx->ensureRegularFontFace();
        }
        
        $ctx->vg->textAlign(VGAlign::LEFT | VGAlign::TOP);
        $ctx->vg->fontSize($this->fontSize);
        $ctx->vg->text($ctx->origin->x + $this->padding->x, $ctx->origin->y + $this->padding->y, $this->text);
    }
}