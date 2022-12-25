<?php

namespace VISU\Graphics;

use VISU\OS\Window;

/**
 * You probably will rise an eyebrow over this one.
 * OpenGL is a state machine, with a global state. So why pass around a state object
 * with seemingly redundant information? Well reading information back from OpenGL
 * can result in a synchronous call to the GPU, and this is a performance hit.
 * Because this library aims to be a high level wrapper and collection of components 
 * we do some sanity checks to avoid redundant calls to OpenGL. 
 * To do these checks we need to know the current state, this where this object comes in.
 * 
 * Additionally as this library tries to be a bit more OOP then the OpenGL API having this 
 * object makes the dependency on the OpenGL state more clear.
 */
class GLState
{   
    /**
     * The Window the current GL context belongs to.
     * 
     * @var null|Window
     */
    public ?Window $window = null;

    /**
     * Currently used shader program object.
     * 
     * **Note:** You should never manually manipulate this value.
     * 
     * @var int
     */
    public int $currentProgram = 0;

    /**
     * Currently bound READ framebuffer object
     * 
     * **Note:** You should never manually manipulate this value.
     * 
     * @var int
     */
    public int $currentReadFramebuffer = 0;

    /**
     * Currently bound DRAW framebuffer object
     * 
     * **Note:** You should never manually manipulate this value.
     * 
     * @var int
     */
    public int $currentDrawFramebuffer = 0;

    /**
     * Currently bound vertex array object
     * 
     * **Note:** You should never manually manipulate this value.
     * 
     * @var int
     */
    public int $currentVertexArray = 0;

    /**
     * Currently bound vertex buffer object
     * 
     * **Note:** You should never manually manipulate this value.
     * 
     * @var int
     */
    public int $currentVertexBuffer = 0;

    /**
     * Currently bound index buffer object
     * 
     * **Note:** You should never manually manipulate this value.
     * 
     * @var int
     */
    public int $currentIndexBuffer = 0;

    /**
     * Currently bound texture object
     * 
     * **Note:** You should never manually manipulate this value.
     * 
     * @var int
     */
    public int $currentTexture = 0;

    /**
     * Currently bound texture unit
     * 
     * **Note:** You should never manually manipulate this value.
     * 
     * @var int
     */
    public int $currentTextureUnit = 0;
}
