<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;

class FUISpace extends FUIView
{
    /**
     * The space simply misuses the padding of the base view as its size
     */
    public function getEstimatedSize(FUIRenderContext $ctx) : Vec2
    {
        return $this->padding;
    }

    /**
     * Renders the current view using the provided context
     */
    public function render(FUIRenderContext $ctx) : void
    {
       // nothing to render, just take up space
    }
}