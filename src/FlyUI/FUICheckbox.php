<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGAlign;
use GL\VectorGraphics\VGColor;
use VISU\OS\MouseButton;

class FUICheckbox extends FUIView
{
    private const FUI_HEIGHT = 20.0;

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

        if ($ctx->triggeredOnce($this->text, $isInside && $ctx->input->isMouseButtonPressed(MouseButton::LEFT))) {
            $this->checked = !$this->checked;
        }

        $ctx->vg->beginPath();
        $ctx->vg->fillColor($this->checked ? VGColor::red() : VGColor::green());
        $ctx->vg->rect($ctx->origin->x, $ctx->origin->y, self::FUI_HEIGHT, self::FUI_HEIGHT);
        $ctx->vg->fill();

        // no pass to parent, as this is a leaf element
        return $height;
    }
}