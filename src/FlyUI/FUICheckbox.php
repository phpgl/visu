<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGAlign;
use GL\VectorGraphics\VGColor;
use VISU\OS\MouseButton;

class FUICheckbox extends FUIView
{
    private const FUI_HEIGHT = 24.0;

    /**
     * Constructs a new view
     */
    public function __construct(
        public string $text,
        public bool &$checked,
    )
    {
        parent::__construct();
    }

    /**
     * Returns the height of the current view and its children
     * This is used for layouting purposes
     */
    public function getEstimatedHeight(FUIRenderContext $ctx) : float
    {
        return self::FUI_HEIGHT;
    }

    /**
     * Renders the current view using the provided context
     */
    public function render(FUIRenderContext $ctx) : float
    {
        $height = $this->getEstimatedHeight($ctx);

        $ctx->containerSize->y = $height;

        $isInside = $ctx->isHovered();

        if ($ctx->triggeredOnce('swt_' . $this->text, $isInside && $ctx->input->isMouseButtonPressed(MouseButton::LEFT))) {
            $this->checked = !$this->checked;
        }

        $switchWidth = self::FUI_HEIGHT * 2;

        // first a background
        $bgColor = $this->checked ? FlyUI::$instance->theme->checkboxActiveBackgroundColor : FlyUI::$instance->theme->checkboxBackgroundColor;
        if ($isInside) {
            $bgColor = $this->checked ? $bgColor->lighten(0.05) : $bgColor->darken(0.05);
        }

        // border 
        $ctx->vg->strokeColor($bgColor->darken(0.1));
        $ctx->vg->strokeWidth(1.0);

        $ctx->vg->beginPath();
        $ctx->vg->fillColor($bgColor);
        $ctx->vg->roundedRect(
            $ctx->origin->x,
            $ctx->origin->y + 1,
            $switchWidth,
            self::FUI_HEIGHT - 2,
            (self::FUI_HEIGHT - 2) * 0.5
        );
        $ctx->vg->stroke();
        $ctx->vg->fill();

        $knobX = $ctx->origin->x + ($this->checked ? $switchWidth - self::FUI_HEIGHT : 0);

        // render a little circle in the middle
        $ctx->vg->beginPath();
        $ctx->vg->fillColor(VGColor::white());
        $ctx->vg->circle(
            $knobX + self::FUI_HEIGHT * 0.5,
            $ctx->origin->y + self::FUI_HEIGHT * 0.5,
            self::FUI_HEIGHT * 0.35
        );
        $ctx->vg->fill();
        $ctx->vg->stroke();

        // render the text next to the switch
        $ctx->ensureFontFace('inter-regular');
        $ctx->vg->textAlign(VGAlign::LEFT | VGAlign::MIDDLE);
        $ctx->vg->fontSize(FlyUI::$instance->theme->fontSize);
        $ctx->vg->fillColor(VGColor::black());
        $ctx->vg->text(
            $ctx->origin->x + $switchWidth + FlyUI::$instance->theme->padding,
            $ctx->origin->y + self::FUI_HEIGHT * 0.5,
            $this->text
        );

        // no pass to parent, as this is a leaf element
        return $height;
    }
}