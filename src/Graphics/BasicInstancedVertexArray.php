<?php

namespace VISU\Graphics;

use GL\Buffer\FloatBuffer;

/**
 * See "BasicVertexArray.php" this is basically the same but with an additional buffer for instanced data.
 * 
 * Keep in bind these basic classes are not meant to be useful for every use case, 
 * If you have to handle more complex vertex layouts you should handle it yourself.
 * 
 * Also here all values are assumed to be floats.
 * 
 * You can specify a simple vertex layout by specifying the element sizes in sequence. Example:
 *    [3, 2] = vec3, vec2 = 5 floats per vertex
 *    [3, 2, 3] = vec3, vec2, vec3 = 8 floats per vertex
 *    [3, 2, 3, 1] = vec3, vec2, vec3, float = 9 floats per vertex
 *    // etc..
 */
class BasicInstancedVertexArray
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
     * The instanced data buffer object from GL
     */
    public readonly int $instanceBuffer;

    /**
     * Vertex count
     */
    private int $vertexCount = 0;

    /**
     * Number of floats per vertex
     */
    private int $floatsPerVertex = 0;

    /**
     * Number of floats per instance
     */
    private int $floatsPerInstance = 0;

    /**
     * Number of instances
     */
    private int $instanceCount = 0;

    /**
     * Constructor
     * 
     * @param GLState $state 
     * @param array<int> $vertexLayout The vertex layout
     * @param array<int> $instanceLayout The instance layout
     * @return void 
     */
    public function __construct(
        private GLState $state, 
        array $vertexLayout,
        array $instanceLayout
    )
    {
        $va = 0;
        $vb = 0;
        $ib = 0;
        glGenVertexArrays(1, $va);
        glGenBuffers(1, $vb);
        glGenBuffers(1, $ib);

        $this->vertexArray = $va;
        $this->vertexBuffer = $vb;
        $this->instanceBuffer = $ib;

        $this->state->bindVertexArray($this->vertexArray);
        $this->state->bindVertexArrayBuffer($this->vertexBuffer);

        // declare the vertex attributes
        $attributePointer = 0;
        $vetexOffset = 0;
        $this->floatsPerVertex = array_sum($vertexLayout);
        foreach($vertexLayout as $size) 
        {
            glVertexAttribPointer($attributePointer, $size, GL_FLOAT, false, $this->floatsPerVertex * GL_SIZEOF_FLOAT, $vetexOffset * GL_SIZEOF_FLOAT);
            glEnableVertexAttribArray($attributePointer);
            $vetexOffset += $size;
            $attributePointer++;
        }

        // declare the instance attributes
        $instanceOffset = 0;
        $this->floatsPerInstance = array_sum($instanceLayout);

        $this->state->bindVertexArrayBuffer($this->instanceBuffer);
        foreach($instanceLayout as $size) 
        {
            glVertexAttribPointer($attributePointer, $size, GL_FLOAT, false, $this->floatsPerInstance * GL_SIZEOF_FLOAT, $instanceOffset * GL_SIZEOF_FLOAT);
            glEnableVertexAttribArray($attributePointer);
            glVertexAttribDivisor($attributePointer, 1);
            $instanceOffset += $size;
            $attributePointer++;
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
        glDeleteBuffers(1, $this->instanceBuffer);
    }

    /**
     * Uploads the vertex data to the GPU
     * 
     * @param FloatBuffer $vertexData The vertex data
     * @return void 
     */
    public function uploadVertexData(FloatBuffer $vertexData) : void
    {
        $this->state->bindVertexArray($this->vertexArray);
        $this->state->bindVertexArrayBuffer($this->vertexBuffer);

        // do a sanity check
        assert($vertexData->size() % $this->floatsPerVertex === 0);

        $this->vertexCount = $vertexData->size() / $this->floatsPerVertex;
        glBufferData(GL_ARRAY_BUFFER, $vertexData, GL_STATIC_DRAW);
    }

    /**
     * Uploads the instance data to the GPU
     * 
     * @param FloatBuffer $instanceData The instance data
     * @return void 
     */
    public function uploadInstanceData(FloatBuffer $instanceData) : void
    {
        $this->state->bindVertexArray($this->vertexArray);
        $this->state->bindVertexArrayBuffer($this->instanceBuffer);

        $this->instanceCount = $instanceData->size() / $this->floatsPerInstance;
        glBufferData(GL_ARRAY_BUFFER, $instanceData, GL_STATIC_DRAW);
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
    public function drawAll(int $drawMode = GL_TRIANGLES) : void
    {
        $this->bind();
        glDrawArraysInstanced($drawMode, 0, $this->vertexCount, $this->instanceCount);
    }

    /**
     * Draws a range of vertices
     *
     * @param integer $vertexOffset
     * @param integer $vertexCount
     * @param integer $instanceCount
     * @param int $drawMode
     * @return void
     */
    public function draw(int $vertexOffset, int $vertexCount, int $instanceCount, int $drawMode = GL_TRIANGLES) : void
    {
        $this->bind();
        glDrawArraysInstanced($drawMode, $vertexOffset, $vertexCount, $instanceCount);
    }
}
