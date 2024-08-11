<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGContext;
use VISU\OS\Input;

class FUIRenderContext
{
    /**
     * Absolute origin of the current view
     */
    public Vec2 $origin;

    /**
     * The size of the current view
     */
    public Vec2 $containerSize;

    /**
     * Initializes the render context
     */
    public function __construct(
        public VGContext $vg,
        public Input $input
    )
    {
        $this->origin = new Vec2(0, 0);
        $this->containerSize = new Vec2(0, 0);
    }
}