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
     * Current cursor position 
     */
    public Vec2 $mousePos;

    /**
     * Current font face
     */
    public string $fontFace = '';

    /**
     * Returns true if the mouse is currently hovering over the current bounds
     */
    public function isHovered() : bool
    {
        return $this->mousePos->x >= $this->origin->x && $this->mousePos->x <= $this->origin->x + $this->containerSize->x
            && $this->mousePos->y >= $this->origin->y && $this->mousePos->y <= $this->origin->y + $this->containerSize->y;
    }

    /**
     * Ensures the given font face is set
     */
    public function ensureFontFace(string $fontFace) : void
    {
        if ($this->fontFace !== $fontFace) {
            $this->vg->fontFace($fontFace);
            $this->fontFace = $fontFace;
        }
    }
    
    /**
     * Sets a static value (persistant data, over multiple frames)
     */
    public function setStaticValue(string $key, mixed $value) : void
    {
        static $persistantData = [];
        $persistantData[$key] = $value;
    }

    /**
     * Gets a static value (persistant data, over multiple frames)
     */
    public function getStaticValue(string $key, mixed $default = null) : mixed
    {
        static $persistantData = [];
        return $persistantData[$key] ?? $default;
    }

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
        $this->mousePos = $this->input->getCursorPosition();
    }
}