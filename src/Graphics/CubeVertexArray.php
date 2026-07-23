<?php

namespace VISU\Graphics;

use GL\Buffer\FloatBuffer;

class CubeVertexArray
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

        // cube vertices (positions only for skybox)
        $buffer = new FloatBuffer([
            // back face
            -1.0, -1.0, -1.0, // bottom-left
             1.0,  1.0, -1.0, // top-right
             1.0, -1.0, -1.0, // bottom-right         
             1.0,  1.0, -1.0, // top-right
            -1.0, -1.0, -1.0, // bottom-left
            -1.0,  1.0, -1.0, // top-left
            
            // front face
            -1.0, -1.0,  1.0, // bottom-left
             1.0, -1.0,  1.0, // bottom-right
             1.0,  1.0,  1.0, // top-right
             1.0,  1.0,  1.0, // top-right
            -1.0,  1.0,  1.0, // top-left
            -1.0, -1.0,  1.0, // bottom-left
            
            // left face
            -1.0,  1.0,  1.0, // top-right
            -1.0,  1.0, -1.0, // top-left
            -1.0, -1.0, -1.0, // bottom-left
            -1.0, -1.0, -1.0, // bottom-left
            -1.0, -1.0,  1.0, // bottom-right
            -1.0,  1.0,  1.0, // top-right
            
            // right face
             1.0,  1.0,  1.0, // top-left
             1.0, -1.0, -1.0, // bottom-right
             1.0,  1.0, -1.0, // top-right         
             1.0, -1.0, -1.0, // bottom-right
             1.0,  1.0,  1.0, // top-left
             1.0, -1.0,  1.0, // bottom-left     
             
            // bottom face
            -1.0, -1.0, -1.0, // top-right
             1.0, -1.0, -1.0, // top-left
             1.0, -1.0,  1.0, // bottom-left
             1.0, -1.0,  1.0, // bottom-left
            -1.0, -1.0,  1.0, // bottom-right
            -1.0, -1.0, -1.0, // top-right
            
            // top face
            -1.0,  1.0, -1.0, // top-left
             1.0,  1.0,  1.0, // bottom-right
             1.0,  1.0, -1.0, // top-right     
             1.0,  1.0,  1.0, // bottom-right
            -1.0,  1.0, -1.0, // top-left
            -1.0,  1.0,  1.0  // bottom-left        
        ]);

        glBufferData(GL_ARRAY_BUFFER, $buffer, GL_STATIC_DRAW);

        // declare the vertex attributes (position only)
        glVertexAttribPointer(0, 3, GL_FLOAT, false, 3 * GL_SIZEOF_FLOAT, 0);
        glEnableVertexAttribArray(0);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        glDeleteVertexArrays(1, $this->vertexArray);
        glDeleteBuffers(1, $this->vertexBuffer);
    }

    /**
     * Binds the vertex array
     */
    public function bind() : void
    {
        $this->state->bindVertexArray($this->vertexArray);
    }

    /**
     * Draws the cube
     */
    public function draw() : void
    {
        $this->bind();
        glDrawArrays(GL_TRIANGLES, 0, 36);
    }
}