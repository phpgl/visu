<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;

class FUISpace extends FUIView
{
    /**
     * The space simply uses the padding values to determine size
     * For horizontal space, it uses left + right padding
     * For vertical space, it uses top + bottom padding
     */
    public function getEstimatedSize(FUIRenderContext $ctx) : Vec2
    {
        return new Vec2($this->padding->x + $this->padding->y, $this->padding->z + $this->padding->w);
    }

    /**
     * Renders the current view using the provided context
     */
    public function render(FUIRenderContext $ctx) : void
    {
       // nothing to render, just take up space
    }
}