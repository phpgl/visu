<?php

namespace VISU\Graphics\Rendering\Renderer\TextLabelRenderer;

use GL\Math\Vec4;
use VISU\Geo\Transform;

class TextLabel
{   
    /**
     * Dirty flag, set to true if the text has changed.
     */
    private bool $isDirty = true;

    /**
     * The color of the text.
     */
    public Vec4 $color;

    /**
     * Constructor
     * 
     * @param Transform $transform 
     * @return void 
     */
    public function __construct(
        public string $text,
        public readonly Transform $transform,
    )
    {
        $this->color = new Vec4(1, 1, 1, 1);
    }
    
    /**
     * Returns boolean true if the text has changed since the last render.
     */
    public function isDirty() : bool
    {
        return $this->isDirty || $this->transform->isDirty;
    }

    /**
     * Mark the text as clean.
     */
    public function markClean() : void
    {
        $this->isDirty = false;
    }

    /**
     * Manually marks the text as dirty.
     */
    public function markDirty() : void
    {
        $this->isDirty = true;
    }

    /**
     * Updates the text and sets the dirty flag.
     */
    public function updateText(string $text) : void
    {
        if ($this->text === $text) {
            return;
        }
        $this->text = $text;
        $this->isDirty = true;
    }

    /**
     * Updates the color and sets the dirty flag.
     */
    public function updateColor(Vec4 $color) : void
    {
        if ($this->color === $color) {
            return;
        }
        $this->color = $color;
        $this->isDirty = true;
    }
}
