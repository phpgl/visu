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

    /**
     * The render targets content scale on the x axis
     */    
    public float $contentScaleX = 1.0;

    /**
     * The render targets content scale on the y axis
     */
    public float $contentScaleY = 1.0;

    /**
     * An array of TextureResource objects that are attached to the render target
     * 
     * @var array<int, TextureResource>
     */
    public array $colorAttachments = [];

    /**
     * Depth attachment
     */
    public ?TextureResource $depthAttachment = null;

    /**
     * Create a depth stencil Renderbuffer
     */
    public bool $createRenderbufferDepthStencil = false;

    /**
     * Create a color Renderbuffer
     */
    public bool $createRenderbufferColor = false;
}
