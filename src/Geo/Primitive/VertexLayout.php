<?php

namespace VISU\Geo\Primitive;

/**
 * Describes the ordered set of attributes a primitive should generate.
 *
 * The attribute cases are re-exported as constants so a layout reads naturally:
 *
 *     $vl = VertexLayout::with([
 *         VertexLayout::position,
 *         VertexLayout::normal,
 *         VertexLayout::uv,
 *     ]);
 *
 * The order of the attributes is the order they are written per vertex.
 */
class VertexLayout
{
    public const position = VertexAttribute::position;
    public const normal = VertexAttribute::normal;
    public const uv = VertexAttribute::uv;

    /**
     * The attributes in write order (deduplicated, order preserved).
     *
     * @var array<VertexAttribute>
     */
    private array $attributes;

    /**
     * Constructs a layout from the given, already normalized, attributes.
     *
     * @param array<VertexAttribute> $attributes
     */
    private function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Builds a layout from the given attributes, removing duplicates while
     * preserving the first occurrence's position.
     *
     * @param array<VertexAttribute> $attributes
     */
    public static function with(array $attributes) : self
    {
        $unique = [];
        foreach ($attributes as $attribute) {
            if (!in_array($attribute, $unique, true)) {
                $unique[] = $attribute;
            }
        }

        return new self($unique);
    }

    /**
     * Returns the attributes in write order.
     *
     * @return array<VertexAttribute>
     */
    public function attributes() : array
    {
        return $this->attributes;
    }

    /**
     * Returns the stride of a single vertex in floats.
     */
    public function stride() : int
    {
        $stride = 0;
        foreach ($this->attributes as $attribute) {
            $stride += $attribute->size();
        }

        return $stride;
    }

    /**
     * Returns whether the given attribute is part of this layout.
     */
    public function has(VertexAttribute $attribute) : bool
    {
        return in_array($attribute, $this->attributes, true);
    }

    /**
     * Returns the float offset of the given attribute inside a vertex, or -1
     * if the attribute is not part of this layout.
     */
    public function offsetOf(VertexAttribute $attribute) : int
    {
        $offset = 0;
        foreach ($this->attributes as $current) {
            if ($current === $attribute) {
                return $offset;
            }
            $offset += $current->size();
        }

        return -1;
    }
}
