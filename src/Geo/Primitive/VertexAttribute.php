<?php

namespace VISU\Geo\Primitive;

/**
 * A single vertex attribute a primitive can emit.
 */
enum VertexAttribute
{
    /**
     * Vertex position (3 floats).
     */
    case position;

    /**
     * Vertex normal (3 floats).
     */
    case normal;

    /**
     * Texture coordinate (2 floats).
     */
    case uv;

    /**
     * Number of floats this attribute occupies inside a vertex.
     */
    public function size() : int
    {
        return match ($this) {
            self::position => 3,
            self::normal => 3,
            self::uv => 2,
        };
    }
}
