<?php

namespace VISU\Graphics\Rendering\Renderer;

use GL\Math\Vec3;

class DebugOverlayText
{   
    /**
     * Constructor 
     */
    public function __construct(
        public string $text,
        public int $offsetX = 0,
        public int $offsetY = 0,
        public ?Vec3 $color = null
    ) {}
}
