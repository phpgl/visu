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
    public int $currentVertexArrayBuffer = 0;

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

    /**
     * Resets the state of this object.
     */
    public function reset() : void
    {
        $this->currentProgram = 0;
        $this->currentReadFramebuffer = 0;
        $this->currentDrawFramebuffer = 0;
        $this->currentVertexArray = 0;
        $this->currentVertexArrayBuffer = 0;
        $this->currentIndexBuffer = 0;
        $this->currentTexture = 0;
        $this->currentTextureUnit = 0;
    }

    /**
     * State aware bind of a vertex array object.
     * 
     * This method will only call glBindVertexArray if the passed vao is not the currently bound one.
     * Use this method instead of glBindVertexArray to avoid redundant calls to OpenGL.
     * 
     * This obviously only works if you use this class to manage your GL state. This is more costly 
     * then just calling glBindVertexArray...
     * 
     * @param int $vao The vertex array object to bind.
     */
    public function bindVertexArray(int $vao) : void
    {
        if ($this->currentVertexArray !== $vao) {
            $this->currentVertexArray = $vao;
            glBindVertexArray($vao);
        }
    }

    /**
     * State aware bind of a vertex buffer object.
     * 
     * This method will only call glBindBuffer if the passed vbo is not the currently bound one.
     * Use this method instead of glBindBuffer to avoid redundant calls to OpenGL.
     * 
     * This obviously only works if you use this class to manage your GL state. This is more costly 
     * then just calling glBindBuffer...
     * 
     * @param int $vbo The vertex buffer object to bind.
     */
    public function bindVertexArrayBuffer(int $vbo) : void
    {
        if ($this->currentVertexArrayBuffer !== $vbo) {
            $this->currentVertexArrayBuffer = $vbo;
            glBindBuffer(GL_ARRAY_BUFFER, $vbo);
        }
    }

    /**
     * State aware bind of a index buffer object.
     * 
     * This method will only call glBindBuffer if the passed ibo is not the currently bound one.
     * Use this method instead of glBindBuffer to avoid redundant calls to OpenGL.
     * 
     * This obviously only works if you use this class to manage your GL state. This is more costly 
     * then just calling glBindBuffer...
     * 
     * @param int $ibo The index buffer object to bind.
     */
    public function bindIndexBuffer(int $ibo) : void
    {
        if ($this->currentIndexBuffer !== $ibo) {
            $this->currentIndexBuffer = $ibo;
            glBindBuffer(GL_ELEMENT_ARRAY_BUFFER, $ibo);
        }
    }
}
