<?php

namespace VISU\Graphics;

use GL\Buffer\FloatBuffer;
use GL\Buffer\UIntBuffer;
use VISU\Geo\AABB;

/**
 * Pure data container for mesh geometry (no GL dependencies).
 * Can be used to generate geometry without an OpenGL context.
 */
class MeshGeometry
{
    public function __construct(
        public readonly FloatBuffer $vertices,
        public readonly UIntBuffer $indices,
        public readonly AABB $aabb,
    ) {
    }

    /**
     * Returns the number of vertices (each vertex = 12 floats)
     */
    public function getVertexCount(): int
    {
        return (int) ($this->vertices->size() / Mesh3D::STRIDE);
    }

    /**
     * Returns the number of indices
     */
    public function getIndexCount(): int
    {
        return $this->indices->size();
    }

    /**
     * Returns the number of triangles
     */
    public function getTriangleCount(): int
    {
        return (int) ($this->indices->size() / 3);
    }

    /**
     * Validates that all indices are within vertex bounds
     */
    public function validate(): bool
    {
        $vertexCount = $this->getVertexCount();
        for ($i = 0; $i < $this->indices->size(); $i++) {
            if ($this->indices[$i] >= $vertexCount) {
                return false;
            }
        }
        return $this->indices->size() % 3 === 0 && $this->vertices->size() % Mesh3D::STRIDE === 0;
    }
}
