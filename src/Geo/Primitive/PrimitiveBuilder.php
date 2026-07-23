<?php

namespace VISU\Geo\Primitive;

use GL\Buffer\FloatBuffer;
use GL\Math\{Vec2, Vec3};

/**
 * Layout aware vertex writer.
 *
 * A primitive computes all attributes it supports for every vertex and hands
 * them to the builder. The builder then writes only the attributes requested by
 * the layout, in layout order, into the target buffer. This keeps the attribute
 * ordering logic in one place instead of duplicating it in every primitive.
 */
class PrimitiveBuilder
{
    /**
     * Constructs a builder writing into the given target buffer.
     */
    public function __construct(
        private VertexLayout $layout,
        private FloatBuffer $target
    )
    {
    }

    /**
     * Writes a single vertex into the target buffer.
     *
     * Only the attributes present in the layout are written. If the layout
     * requests an attribute that was not provided (null), a PrimitiveException
     * is thrown rather than emitting silently corrupt data.
     *
     * @throws PrimitiveException
     */
    public function addVertex(Vec3 $position, ?Vec3 $normal = null, ?Vec2 $uv = null) : void
    {
        foreach ($this->layout->attributes() as $attribute) {
            switch ($attribute) {
                case VertexAttribute::position:
                    $this->target->pushVec3($position);
                    break;

                case VertexAttribute::normal:
                    if ($normal === null) {
                        throw new PrimitiveException('The layout requests a normal but the primitive did not provide one.');
                    }
                    $this->target->pushVec3($normal);
                    break;

                case VertexAttribute::uv:
                    if ($uv === null) {
                        throw new PrimitiveException('The layout requests a UV but the primitive did not provide one.');
                    }
                    $this->target->pushVec2($uv);
                    break;
            }
        }
    }
}
