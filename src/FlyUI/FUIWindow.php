<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGColor;

class FUIWindow extends FUIView
{
    /**
     * Constructs a new view
     */
    public function __construct(
        public string $windowTitle,
        public ?Vec2 $pos, 
        public ?Vec2 $size,
        public bool $isDraggable = true,
        public ?FUILayout $layout = null,
    )
    {
        parent::__construct(FlyUI::$instance->theme->windowPadding);
    }

    /**
     * Returns the height of the current view and its children
     * This is used for layouting purposes
     */
    public function getEstimatedHeight() : float
    {
        if ($this->layout) {
            return $this->layout->getEstimatedHeight();
        }
        elseif ($this->size) {
            return $this->size->y;
        }

        return parent::getEstimatedHeight();
    }

    /**
     * Renders the current view using the provided context
     */
    public function render(FUIRenderContext $ctx) : void
    {
        $card = new FUICard(
            VGColor::white(),
            10.0
        );

        $card->render($ctx);

        parent::render($ctx);
    }
}