<?php

namespace VISU\Graphics;

use Exception;
use GL\Math\Vec2;
use VISU\Exception\VISUException;

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
     * Returns the framebuffer only if it is of instance "Framebuffer" and so is offscreen
     */
    public function offscreenFramebuffer(): Framebuffer
    {
        if (!($this->framebuffer instanceof Framebuffer)) {
            return throw new VISUException("RenderTarget does not have an offscreen framebuffer");
        }

        return $this->framebuffer;;
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

    /**
     * Bind the framebuffer for reading 
     */
    public function bindForRead(): void
    {
        $this->framebuffer->bind(FramebufferTarget::READ);
    }

    /**
     * Resizes the render target to new dimensions
     * Note: This only updates the internal dimensions and viewport,
     * it does not resize any attached textures or renderbuffers
     * 
     * @param int $width new width in pixels
     * @param int $height new height in pixels
     */
    public function resize(int $width, int $height): void
    {
        $this->width = $width;
        $this->height = $height;
        
        // update the viewport if this render target is currently active
        $this->framebuffer->bind();
        $this->updateViewport();

        if ($this->framebuffer instanceof Framebuffer) {
            $this->framebuffer->resizeAttachments($width, $height);
        }
    }
}
