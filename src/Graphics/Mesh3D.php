<?php

namespace VISU\Graphics;

use GL\Buffer\FloatBuffer;
use GL\Buffer\UIntBuffer;
use VISU\Geo\AABB;

class Mesh3D
{
    /**
     * Vertex format: position(3) + normal(3) + uv(2) + tangent(4) = 12 floats
     */
    const STRIDE = 12;
    const STRIDE_BYTES = 48; // 12 * 4

    const ATTRIB_POSITION = 0;
    const ATTRIB_NORMAL = 1;
    const ATTRIB_UV = 2;
    const ATTRIB_TANGENT = 3;

    private int $vertexArray = 0;
    private int $vertexBuffer = 0;
    private int $indexBuffer = 0;

    private int $vertexCount = 0;
    private int $indexCount = 0;

    public readonly Material $material;
    public readonly AABB $aabb;

    public function __construct(
        private GLState $gl,
        Material $material,
        AABB $aabb,
    ) {
        $this->material = $material;
        $this->aabb = $aabb;

        glGenVertexArrays(1, $this->vertexArray);
        glGenBuffers(1, $this->vertexBuffer);

        $this->gl->bindVertexArray($this->vertexArray);
        $this->gl->bindVertexArrayBuffer($this->vertexBuffer);

        // position (vec3)
        glVertexAttribPointer(self::ATTRIB_POSITION, 3, GL_FLOAT, false, self::STRIDE_BYTES, 0);
        glEnableVertexAttribArray(self::ATTRIB_POSITION);

        // normal (vec3)
        glVertexAttribPointer(self::ATTRIB_NORMAL, 3, GL_FLOAT, false, self::STRIDE_BYTES, 3 * GL_SIZEOF_FLOAT);
        glEnableVertexAttribArray(self::ATTRIB_NORMAL);

        // uv (vec2)
        glVertexAttribPointer(self::ATTRIB_UV, 2, GL_FLOAT, false, self::STRIDE_BYTES, 6 * GL_SIZEOF_FLOAT);
        glEnableVertexAttribArray(self::ATTRIB_UV);

        // tangent (vec4 — xyz = tangent direction, w = handedness)
        glVertexAttribPointer(self::ATTRIB_TANGENT, 4, GL_FLOAT, false, self::STRIDE_BYTES, 8 * GL_SIZEOF_FLOAT);
        glEnableVertexAttribArray(self::ATTRIB_TANGENT);
    }

    /**
     * Uploads vertex data to the GPU
     */
    public function uploadVertices(FloatBuffer $vertices): void
    {
        $this->vertexCount = (int)($vertices->size() / self::STRIDE);

        $this->gl->bindVertexArray($this->vertexArray);
        $this->gl->bindVertexArrayBuffer($this->vertexBuffer);
        glBufferData(GL_ARRAY_BUFFER, $vertices, GL_STATIC_DRAW);
    }

    /**
     * Uploads index data to the GPU (optional, for indexed rendering)
     */
    public function uploadIndices(UIntBuffer $indices): void
    {
        $this->indexCount = $indices->size();

        if ($this->indexBuffer === 0) {
            glGenBuffers(1, $this->indexBuffer);
        }

        $this->gl->bindVertexArray($this->vertexArray);
        glBindBuffer(GL_ELEMENT_ARRAY_BUFFER, $this->indexBuffer);
        glBufferData(GL_ELEMENT_ARRAY_BUFFER, $indices, GL_STATIC_DRAW);
    }

    /**
     * Binds the mesh VAO for rendering
     */
    public function bind(): void
    {
        $this->gl->bindVertexArray($this->vertexArray);
    }

    /**
     * Draws the mesh
     */
    public function draw(): void
    {
        $this->gl->bindVertexArray($this->vertexArray);

        if ($this->indexCount > 0) {
            glDrawElements(GL_TRIANGLES, $this->indexCount, GL_UNSIGNED_INT, 0);
        } else {
            glDrawArrays(GL_TRIANGLES, 0, $this->vertexCount);
        }
    }

    public function getVertexCount(): int
    {
        return $this->vertexCount;
    }

    public function getIndexCount(): int
    {
        return $this->indexCount;
    }

    public function isIndexed(): bool
    {
        return $this->indexCount > 0;
    }
}
