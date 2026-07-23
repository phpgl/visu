<?php

namespace VISU\System\VISULowPoly;

use GL\Buffer\FloatBuffer;
use VISU\Graphics\GLState;

/**
 * A vertex buffer designed for the VISU low poly rendering pipeline.
 * 
 * We bake the material information into the vertex buffer for simplicity, 
 * if you require sepcialized materials, handle them yourself.
 * 
 * This vertex buffer follows a static layout of:
 *  - [3: pos, 3: norm, 3: color, 1: shinyness] = stride of 10 floats per vertex
 */
class LPVertexBuffer
{
     /**
     * The vertex array object from GL
     */
    private int $vertexArray;

    /**
     * The vertex buffer object from GL
     */
    private int $vertexBuffer;

    /**
     * The internal buffer holding the vertex data before upload
     */
    private FloatBuffer $buffer;

    /**
     * The size of a single vertex in floats and bytes
     */
    public const STRIDE = 14;
    public const STRIDE_BYTES = 14 * GL_SIZEOF_FLOAT;

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
        $this->buffer = new FloatBuffer();

        glGenVertexArrays(1, $this->vertexArray);
        glGenBuffers(1, $this->vertexBuffer);
        $this->state->bindVertexArray($this->vertexArray);
        $this->state->bindVertexArrayBuffer($this->vertexBuffer);

        // declare the vertex attributes
        // position, normal
        glVertexAttribPointer(0, 3, GL_FLOAT, false, self::STRIDE_BYTES, 0);
        glEnableVertexAttribArray(0);
        glVertexAttribPointer(1, 3, GL_FLOAT, false, self::STRIDE_BYTES, 3 * GL_SIZEOF_FLOAT);
        glEnableVertexAttribArray(1);
        // color
        glVertexAttribPointer(2, 3, GL_FLOAT, false, self::STRIDE_BYTES, 6 * GL_SIZEOF_FLOAT);
        glEnableVertexAttribArray(2);
        // roughness
        glVertexAttribPointer(3, 1, GL_FLOAT, false, self::STRIDE_BYTES, 9 * GL_SIZEOF_FLOAT);
        glEnableVertexAttribArray(3);
        // metallic
        glVertexAttribPointer(4, 1, GL_FLOAT, false, self::STRIDE_BYTES, 10 * GL_SIZEOF_FLOAT);
        glEnableVertexAttribArray(4);
        // emissive
        glVertexAttribPointer(5, 3, GL_FLOAT, false, self::STRIDE_BYTES, 11 * GL_SIZEOF_FLOAT);
        glEnableVertexAttribArray(5);
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
     * Returns the vertex buffer object ID
     */
    public function getVertexBufferId() : int
    {
        return $this->vertexBuffer;
    }

    /**
     * Returns the vertex array object ID
     */
    public function getVertexArrayId() : int
    {
        return $this->vertexArray;
    }

    /**
     * Appends the given vertex data buffer to this vertex buffer's internal buffer
     * 
     * @return int The offset (in vertices) where the appended data starts
     */
    public function appennd(FloatBuffer $buffer) : int
    {
        // sanity check
        if ($buffer->size() % self::STRIDE !== 0) {
            throw new LPException(sprintf(
                "Attempted to append vertex buffer with size %d which is not a multiple of the vertex stride %d",
                $buffer->size(),
                self::STRIDE
            ));
        }

        $this->buffer->append($buffer);

        // return the offset in vertices
        return ($this->buffer->size() - $buffer->size()) / self::STRIDE;
    }

    /**
     * Upload the internal buffer to the GPU
     */
    public function upload() : void
    {
        $this->state->bindVertexArray($this->vertexArray);
        $this->state->bindVertexArrayBuffer($this->vertexBuffer);
        glBufferData(GL_ARRAY_BUFFER, $this->buffer, GL_STATIC_DRAW);
    }

    /**
     * Binds the vertex array
     */
    public function bind() : void
    {
        $this->state->bindVertexArray($this->vertexArray);
    }
}