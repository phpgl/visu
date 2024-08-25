<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGAlign;
use GL\VectorGraphics\VGColor;

class FUIButton extends FUIView
{
    public VGColor $backgroundColor;

    public VGColor $hoverBackgroundColor;

    public VGColor $textColor;

    public float $borderRadius;

    public float $fontSize;

    /**
     * Constructs a new view
     */
    public function __construct(
        public string $text,
    )
    {
        parent::__construct(FlyUI::$instance->theme->buttonPadding);

        $this->backgroundColor = FlyUI::$instance->theme->buttonPrimaryBackgroundColor;
        $this->hoverBackgroundColor = FlyUI::$instance->theme->buttonPrimaryHoverBackgroundColor;
        $this->textColor = FlyUI::$instance->theme->buttonPrimaryTextColor;
        $this->borderRadius = FlyUI::$instance->theme->buttonBorderRadius;
        $this->fontSize = FlyUI::$instance->theme->buttonFontSize;
    }

    /**
     * Returns the height of the current view and its children
     * This is used for layouting purposes
     */
    public function getEstimatedHeight(FUIRenderContext $ctx) : float
    {
        return $this->fontSize + $this->padding->y * 2;
    }

    /**
     * Returns the width of the current view and its children
     * 
     * Note: This is used for layouting in some sizing modes
     */
    public function getEstimatedWidth(FUIRenderContext $ctx) : float
    {
        return $ctx->vg->textBounds(0, 0, $this->text) + $this->padding->x * 2;
    }

    /**
     * Renders the current view using the provided context
     */
    public function render(FUIRenderContext $ctx) : float
    {
        $height = $this->getEstimatedHeight($ctx);
        $width = $this->getEstimatedWidth($ctx);

        // update the container size based on the determined height
        $ctx->containerSize->y = $height;
        $ctx->containerSize->x = $width;

        // render the button background
        $ctx->vg->beginPath();
        $ctx->vg->fillColor($this->backgroundColor);

        if ($ctx->isHovered()) {
            $ctx->vg->fillColor($this->hoverBackgroundColor);
        }

        $ctx->vg->roundedRect(
            $ctx->origin->x,
            $ctx->origin->y,
            $ctx->containerSize->x,
            $height,
            $this->borderRadius
        );
        $ctx->vg->fill();

        // move origin to the center of the button
        $ctx->origin->x = $ctx->origin->x + $ctx->containerSize->x * 0.5;
        $ctx->origin->y = $ctx->origin->y + $height * 0.5;

        $ctx->vg->textAlign(VGAlign::CENTER | VGAlign::MIDDLE);
        $ctx->vg->fontSize($this->fontSize);
        $ctx->vg->fillColor($this->textColor);
        $ctx->vg->text($ctx->origin->x, $ctx->origin->y, $this->text);

        // no pass to parent, as this is a leaf element
        return $height;
    }
}