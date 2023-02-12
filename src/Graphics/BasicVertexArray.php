<?php

namespace VISU\Graphics;

use GL\Buffer\FloatBuffer;

/**
 * A simple wrapper around a vertex array and vertex buffer
 * That assumes all values to be floats. 
 * 
 * You can specify a simple vertex layout by specifying the element sizes in sequence. Example:
 *    [3, 2] = vec3, vec2 = 5 floats per vertex
 *    [3, 2, 3] = vec3, vec2, vec3 = 8 floats per vertex
 *    [3, 2, 3, 1] = vec3, vec2, vec3, float = 9 floats per vertex
 *    // etc..
 */
class BasicVertexArray
{
    /**
     * The vertex array object from GL
     */
    public readonly int $vertexArray;

    /**
     * The vertex buffer object from GL
     */
    public readonly int $vertexBuffer;

    /**
     * Vertex count
     */
    private int $vertexCount = 0;

    /**
     * Number of floats per vertex
     */
    private int $floatsPerVertex = 0;

    /**
     * Constructor
     * 
     * @param GLState $state 
     * @param array<int> $vertexLayout The vertex layout
     * @return void 
     */
    public function __construct(
        private GLState $state, 
        array $vertexLayout
    )
    {
        $va = 0;
        $vb = 0;
        glGenVertexArrays(1, $va);
        glGenBuffers(1, $vb);

        $this->vertexArray = $va;
        $this->vertexBuffer = $vb;

        $this->state->bindVertexArray($this->vertexArray);
        $this->state->bindVertexArrayBuffer($this->vertexBuffer);

        // declare the vertex attributes
        $vertexPointer = 0;
        $vetexOffset = 0;
        $this->floatsPerVertex = array_sum($vertexLayout);
        foreach($vertexLayout as $size) 
        {
            glVertexAttribPointer($vertexPointer, $size, GL_FLOAT, false, $this->floatsPerVertex * GL_SIZEOF_FLOAT, $vetexOffset * GL_SIZEOF_FLOAT);
            glEnableVertexAttribArray($vertexPointer);
            $vetexOffset += $size;
            $vertexPointer++;
        }
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
     * Uploads the vertex data to the GPU
     * 
     * @param FloatBuffer $vertexData The vertex data
     * @return void 
     */
    public function upload(FloatBuffer $vertexData) : void
    {
        $this->state->bindVertexArray($this->vertexArray);
        $this->state->bindVertexArrayBuffer($this->vertexBuffer);

        $this->vertexCount = $vertexData->size() / $this->floatsPerVertex;
        glBufferData(GL_ARRAY_BUFFER, $vertexData, GL_STATIC_DRAW);
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
     * Draws all vertices
     */
    public function drawAll() : void
    {
        $this->bind();
        glDrawArrays(GL_TRIANGLES, 0, $this->vertexCount);
    }

    /**
     * Draws a range of vertices
     * 
     * @param int $offset The offset
     * @param int $count The count
     */
    public function draw(int $offset, int $count) : void
    {
        $this->bind();
        glDrawArrays(GL_TRIANGLES, $offset, $count);
    }
}
