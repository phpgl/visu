<?php

namespace VISU\Graphics;

use GL\Buffer\FloatBuffer;

class QuadVertexArray
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

        // two triangles to form a quad (CCW)
        $buffer = new FloatBuffer([
            // positions     // texture Coords
            -1.0,  1.0, 0.0,  0.0, 1.0, // top left
            -1.0, -1.0, 0.0,  0.0, 0.0, // bottom left
             1.0,  1.0, 0.0,  1.0, 1.0, // top right

             1.0, -1.0, 0.0,  1.0, 0.0, // bottom right
             1.0,  1.0, 0.0,  1.0, 1.0, // top right
            -1.0, -1.0, 0.0,  0.0, 0.0, // bottom left
        ]);

        glBufferData(GL_ARRAY_BUFFER, $buffer, GL_STATIC_DRAW);

        // declare the vertex attributes
        glVertexAttribPointer(0, 3, GL_FLOAT, false, 5 * GL_SIZEOF_FLOAT, 0);
        glEnableVertexAttribArray(0);
        glVertexAttribPointer(1, 2, GL_FLOAT, false, 5 * GL_SIZEOF_FLOAT, 3 * GL_SIZEOF_FLOAT);
        glEnableVertexAttribArray(1);
    }

    /**
     * Destructor
     * 
     * @return void 
     */
    public function __destruct()
    {
        glDeleteVertexArrays(1, $this->vertexArray);
        glDeleteBuffers(1, $this->vertexBuffer);
    }

    /**
     * Binds the vertex array
     * 
     * @return void 
     */
    public function bind() : void
    {
        $this->state->bindVertexArray($this->vertexArray);
    }

    /**
     * Draws the quad
     */
    public function draw() : void
    {
        $this->bind();
        glDrawArrays(GL_TRIANGLE_STRIP, 0, 6);
    }
}
