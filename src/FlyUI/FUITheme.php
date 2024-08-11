<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;

class FUITheme
{   
    /**
     * The general padding used to space elements
     */
    public float $padding = 10.0;

    /**
     * The padding used for windows
     */
    public Vec2 $windowPadding;

    /**
     * Constructs a new theme
     */
    public function __construct() {
        $this->windowPadding = new Vec2($this->padding, $this->padding);
    }
}