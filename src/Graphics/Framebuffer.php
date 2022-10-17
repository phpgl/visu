<?php

namespace VISU\Graphics;

class Framebuffer extends AbstractFramebuffer
{
    /**
     * Creates a new framebuffer object
     * 
     * @param GLState $gl
     */
    final public function __construct(GLState $gl)
    {
        glGenFramebuffers(1, $id);
        parent::__construct($gl, $id);
    }

    /**
     * Destructor 
     */
    final public function __destruct()
    {
        glDeleteFramebuffers(1, $this->id);
    }
}
