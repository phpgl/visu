<?php

namespace VISU\Graphics\Rendering\Resource;

use VISU\Graphics\Rendering\RenderResource;

class RenderTargetResource extends RenderResource
{
    /**
     * The render targets width (in pixels)
     */
    public int $width = 1280;

    /**
     * The render targets height (in pixels)
     */
    public int $height = 720;
}
