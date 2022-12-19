<?php

namespace VISU\Graphics;

use GL\Math\Vec4;

abstract class AbstractFramebuffer
{
    /**
     * OpenGL framebuffer ID
     */
    public readonly int $id;

    /**
     * GL State instance as we modify the GL state
     */
    protected GLState $gl;

    /**
     * Framebuffers clearing color
     * 
     * @var Vec4
     */
    public Vec4 $clearColor;

    /**
     * Constructor
     */
    protected function __construct(GLState $gl, int $id)
    {
        $this->gl = $gl;
        $this->id = $id;
        
        // always set the default clear color 
        $this->clearColor = new Vec4(0.0, 0.0, 0.0, 1.0);
    }

    /**
     * Binds the framebuffer to the current context
     * 
     * @param FramebufferTarget $target Specifies the target to which the framebuffer is bound.
     */
    public function bind(FramebufferTarget $target = FramebufferTarget::READ_DRAW): void
    {
        if ($target === FramebufferTarget::READ_DRAW) {
            if ($this->gl->currentReadFramebuffer !== $this->id || $this->gl->currentDrawFramebuffer !== $this->id) {
                glBindFramebuffer(GL_FRAMEBUFFER, $this->id);
                $this->gl->currentReadFramebuffer = $this->id;
                $this->gl->currentDrawFramebuffer = $this->id;
            }
        }
        elseif ($target === FramebufferTarget::READ) {
            if ($this->gl->currentReadFramebuffer !== $this->id) {
                glBindFramebuffer(GL_READ_FRAMEBUFFER, $this->id);
                $this->gl->currentReadFramebuffer = $this->id;
            }
        }
        elseif ($target === FramebufferTarget::DRAW) {
            if ($this->gl->currentDrawFramebuffer !== $this->id) {
                glBindFramebuffer(GL_DRAW_FRAMEBUFFER, $this->id);
                $this->gl->currentDrawFramebuffer = $this->id;
            }
        }
    }

    /**
     * Clears the framebuffer 
     * Note: This will bind the framebuffer to the current context!
     * 
     * @return void 
     */
    public function clear(int $clearbits = GL_COLOR_BUFFER_BIT | GL_DEPTH_BUFFER_BIT | GL_STENCIL_BUFFER_BIT): void
    {
        $this->bind();

        glClearColor($this->clearColor->x, $this->clearColor->y, $this->clearColor->z, $this->clearColor->w);
        glClear($clearbits);
    }

    /**
     * Returns the current framebuffer statsus 
     * Note: This will bind the framebuffer to the current context!
     * 
     * Status can be one of:
     * - GL_FRAMEBUFFER_COMPLETE
     * - GL_FRAMEBUFFER_UNDEFINED
     * - GL_FRAMEBUFFER_INCOMPLETE_ATTACHMENT
     * - GL_FRAMEBUFFER_INCOMPLETE_MISSING_ATTACHMENT
     * - GL_FRAMEBUFFER_INCOMPLETE_DRAW_BUFFER
     * - GL_FRAMEBUFFER_INCOMPLETE_READ_BUFFER
     * - GL_FRAMEBUFFER_UNSUPPORTED
     * - GL_FRAMEBUFFER_INCOMPLETE_MULTISAMPLE
     * - GL_FRAMEBUFFER_INCOMPLETE_LAYER_TARGETS
     * 
     * @return int 
     */
    public function getStatus() : int
    {
        $this->bind();
        return glCheckFramebufferStatus(GL_FRAMEBUFFER);
    }

    /**
     * Returns the given framebuffer status as string
     * 
     * @param int $status 
     * @return string 
     */
    public function getFramebufferStatusString(int $status) : string
    {
        switch ($status) {
            case GL_FRAMEBUFFER_COMPLETE:
                return "GL_FRAMEBUFFER_COMPLETE";
            case GL_FRAMEBUFFER_UNDEFINED:
                return "GL_FRAMEBUFFER_UNDEFINED";
            case GL_FRAMEBUFFER_INCOMPLETE_ATTACHMENT:
                return "GL_FRAMEBUFFER_INCOMPLETE_ATTACHMENT";
            case GL_FRAMEBUFFER_INCOMPLETE_MISSING_ATTACHMENT:
                return "GL_FRAMEBUFFER_INCOMPLETE_MISSING_ATTACHMENT";
            case GL_FRAMEBUFFER_INCOMPLETE_DRAW_BUFFER:
                return "GL_FRAMEBUFFER_INCOMPLETE_DRAW_BUFFER";
            case GL_FRAMEBUFFER_INCOMPLETE_READ_BUFFER:
                return "GL_FRAMEBUFFER_INCOMPLETE_READ_BUFFER";
            case GL_FRAMEBUFFER_UNSUPPORTED:
                return "GL_FRAMEBUFFER_UNSUPPORTED";
            case GL_FRAMEBUFFER_INCOMPLETE_MULTISAMPLE:
                return "GL_FRAMEBUFFER_INCOMPLETE_MULTISAMPLE";
            case GL_FRAMEBUFFER_INCOMPLETE_LAYER_TARGETS:
                return "GL_FRAMEBUFFER_INCOMPLETE_LAYER_TARGETS";
            default:
                return "Unknown status";
        }
    }

    /**
     * Performs a status check on the framebuffer and returns boolean result.
     * 
     * If you wan't to get the error code and or message, the first two parameters are passed by reference.
     * 
     * @param int $status 
     * @param string $error 
     * @return bool 
     */
    public function isValid(?int &$status = null, ?string &$error = null): bool 
    {
        $status = $this->getStatus();

        if ($status !== GL_FRAMEBUFFER_COMPLETE) {
            $error = "Framebuffer is not complete: " . $this->getFramebufferStatusString($status);
            return false;
        }
        return true;
    }
}
