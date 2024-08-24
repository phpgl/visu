<?php

namespace VISU\Graphics\Rendering\Resource;

use GL\Math\Vec2;
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

    /**
     * Returns the render targets logical width (in points)
     */
    public function effectiveWidth(): int
    {
        return (int) ($this->width / $this->contentScaleX);
    }

    /**
     * Returns the render targets logical height (in points)
     */
    public function effectiveHeight(): int
    {
        return (int) ($this->height / $this->contentScaleY);
    }

    /**
     * Returns a Vec2 with effectiveWidth and effectiveHeight
     */
    public function effectiveSizeVec(): Vec2
    {
        return new Vec2($this->effectiveWidth(), $this->effectiveHeight());
    }
}
