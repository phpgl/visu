<?php

namespace VISU\Quickstart\Render;

use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\TextureResource;

class QuickstartPassData
{
    /**
     * The render target the quickstart should render on
     */
    public RenderTargetResource $renderTarget;

    /**
     * The texture resource that will be drawn on screen
     * 
     * You can replace this for example with parts of your G-Buffer for debugging etc..
     */
    public TextureResource $outputTexture;
}