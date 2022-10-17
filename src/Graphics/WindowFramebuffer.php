<?php

namespace VISU\Graphics;

/**
 * The window frame buffer is the default framebuffer.
 * It is the framebuffer that is used when no other framebuffer is bound.
 * To be able to provide a bit of abstraction, we create a class for it. 
 */
class WindowFramebuffer extends AbstractFramebuffer
{
    final public function __construct(GLState $gl)
    {
        parent::__construct($gl, 0); // window framebuffer / aka main framebuffer is always 0
    }
}
