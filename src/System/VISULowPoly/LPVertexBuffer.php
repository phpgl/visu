<?php

namespace VISU\System\VISULowPoly;

use GL\Buffer\FloatBuffer;
use VISU\Graphics\GLState;

class LPVertexBuffer
{
     /**
     * The vertex array object from GL
     * 
     * @var int
     */
    private int $vertexArray;

    /**
     * The vertex buffer object from GL
     * 
     * @var int
     */
    private int $vertexBuffer;

    /**
     * Constructor
     * 
     * @param GLState $state 
     * @return void 
     */
    public function __construct(
        private GLState $state
    )
    {
        $this->vertexArray = 0;
        $this->vertexBuffer = 0;

        glGenVertexArrays(1, $this->vertexArray);
        glGenBuffers(1, $this->vertexBuffer);
        $this->state->bindVertexArray($this->vertexArray);
        $this->state->bindVertexArrayBuffer($this->vertexBuffer);

        // declare the vertex attributes
        // position, normal
        glVertexAttribPointer(0, 3, GL_FLOAT, false, 6 * GL_SIZEOF_FLOAT, 0);
        glEnableVertexAttribArray(0);
        glVertexAttribPointer(1, 3, GL_FLOAT, false, 6 * GL_SIZEOF_FLOAT, 3 * GL_SIZEOF_FLOAT);
        glEnableVertexAttribArray(1);
    }

    /**
     * Uploads the given data to the GPU
     */
    public function uploadData(FloatBuffer $buffer) : void
    {
        $this->state->bindVertexArray($this->vertexArray);
        $this->state->bindVertexArrayBuffer($this->vertexBuffer);
        glBufferData(GL_ARRAY_BUFFER, $buffer, GL_STATIC_DRAW);
    }

    /**
     * Binds the vertex array
     */
    public function bind() : void
    {
        $this->state->bindVertexArray($this->vertexArray);
    }
}