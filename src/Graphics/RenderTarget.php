<?php

namespace VISU\Graphics;

use Exception;
use GL\Math\Vec2;

class RenderTarget
{   
    /**
     * The render targets frame buffer
     */
    private AbstractFramebuffer $framebuffer;

    /**
     * The render targets pixel density (for high dpi displays) on the x axis
     */
    public float $contentScaleX = 1.0;

    /**
     * The render targets pixel density (for high dpi displays) on the y axis
     */
    public float $contentScaleY = 1.0;

    /**
     * Constrcutor
     */
    public function __construct(
        protected int $width, 
        protected int $height,
        AbstractFramebuffer $framebuffer 
    )
    {
        $this->framebuffer = $framebuffer;
    }

    /**
     * Returns the render targets width in device pixels
     * 
     * @return int
     */
    public function width(): int
    {
        return $this->width;
    }

    /**
     * Returns the render targets height in device pixels
     * 
     * @return int
     */
    public function height(): int
    {
        return $this->height;
    }

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

    /**
     * Returns the render targets framebuffer
     */
    public function framebuffer(): AbstractFramebuffer
    {
        return $this->framebuffer;
    }

    /**
     * Returns boolean if the render target is an offscreen render target
     */
    public function isOffscreen(): bool
    {
        return $this->framebuffer instanceof Framebuffer;
    }

    /**
     * Updates the viewport to the render targets dimensions
     */
    public function updateViewport(): void
    {
        glViewport(0, 0, $this->width, $this->height);
    }

    /**
     * Prepares a render pass to the render target
     * This will bind the render targets framebuffer, update the viewport
     */
    public function preparePass(): void
    {
        // if ($this->framebuffer->bind()) {
        //     $this->updateViewport(); // we only want to update the viewport if we actually bound the framebuffer
        // }
        $this->framebuffer->bind();
        $this->updateViewport();
    }
}
