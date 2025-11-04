<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGAlign;
use GL\VectorGraphics\VGColor;

/**
 * How is a label different from a FUIText?
 *
 * A label is supposed to be used to label form inputs in a uniform matter. Thats 
 * why a label has a fixed style according to the FlyUI theme.
 */
class FUILabel extends FUIView
{
    /**
     * Constructs a new view
     */
    public function __construct(
        public string $text,
    )
    {
        parent::__construct();
    }

    /**
     * Return the label height (seperate method because its static)
     */
    public static function getLabelHeight() : float
    {
        return FlyUI::$instance->theme->labelHeight;
    }

    /**
     * Returns the height of the current view and its children
     * 
     * Note: This is used for layouting in some sizing modes
     */
    public function getEstimatedSize(FUIRenderContext $ctx) : Vec2
    {
        $theme = FlyUI::$instance->theme;

        // get the text size
        $ctx->ensureSemiBoldFontFace();
        $ctx->vg->fontSize($theme->labelFontSize);

        $textWidth = $ctx->vg->textBounds(0, 0, $this->text);
        
        return new Vec2($textWidth, $theme->labelHeight);
    }

    /**
     * Renders the current view using the provided context
     */
    public function render(FUIRenderContext $ctx) : void
    {
        $theme = FlyUI::$instance->theme;

        $ctx->ensureSemiBoldFontFace();
        $ctx->vg->fontSize($theme->labelFontSize);
        $ctx->vg->fillColor($theme->labelTextColor);
        $ctx->vg->textAlign(VGAlign::LEFT | VGAlign::TOP);
        $ctx->vg->text($ctx->origin->x, $ctx->origin->y, $this->text);
    }
}