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
     * By default views are stacked vertically so we need to keep track of the vertical offset
     */
    public float $verticalOffset = 0;

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