<?php

namespace VISU\Graphics;

use Exception;

class RenderTarget
{   
    /**
     * The render targets frame buffer
     */
    private AbstractFramebuffer $framebuffer;

    /**
     * Constrcutor
     */
    public function __construct(
        GLState $gl, 
        protected int $width, 
        protected int $height,
        ?AbstractFramebuffer $framebuffer = null, 
    )
    {
        if ($framebuffer === null) {
            $framebuffer = new Framebuffer($gl);
        }

        $this->framebuffer = $framebuffer;
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
        $this->framebuffer->bind();
        $this->updateViewport();
    }
}
