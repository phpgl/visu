<?php

namespace VISU\Geo\Primitive;

use GL\Buffer\FloatBuffer;
use GL\Math\{Vec2, Vec3};

/**
 * Generates a cylinder aligned to the Y axis, centered at the origin, as a non
 * indexed triangle list.
 *
 * The side uses radial normals, the optional caps use flat normals. Side UVs
 * wrap around the circumference (u) and along the height (v); cap UVs project
 * the disk into the 0..1 range.
 */
class Cylinder
{
    /**
     * Writes a cylinder into the target buffer.
     *
     * @param VertexLayout $layout The attributes to emit
     * @param FloatBuffer $target The buffer to append the vertices to
     * @param float $radius The radius of the cylinder
     * @param float $height The height along the y axis
     * @param int $segments Number of segments around the circumference (>= 3)
     * @param bool $caps Whether to generate the top and bottom caps
     */
    public static function make(
        VertexLayout $layout,
        FloatBuffer $target,
        float $radius = 1.0,
        float $height = 1.0,
        int $segments = 32,
        bool $caps = true
    ) : void
    {
        $builder = new PrimitiveBuilder($layout, $target);

        $segments = max(3, $segments);
        $hy = $height * 0.5;

        // reserve space up front so the target does not reallocate while filling:
        // 6 side verts per segment, plus 3 per cap when caps are requested
        $vertexCount = $segments * 6 + ($caps ? $segments * 6 : 0);
        $target->reserve($target->size() + $vertexCount * $layout->stride());

        // trig table for the segment angle, one extra entry closes the seam
        $cosTheta = [];
        $sinTheta = [];
        for ($seg = 0; $seg <= $segments; $seg++) {
            $theta = 2.0 * M_PI * $seg / $segments;
            $cosTheta[$seg] = cos($theta);
            $sinTheta[$seg] = sin($theta);
        }

        $topNormal = new Vec3(0.0, 1.0, 0.0);
        $topCenter = new Vec3(0.0, $hy, 0.0);
        $bottomNormal = new Vec3(0.0, -1.0, 0.0);
        $bottomCenter = new Vec3(0.0, -$hy, 0.0);

        for ($seg = 0; $seg < $segments; $seg++) {
            $c0 = $cosTheta[$seg];
            $s0 = $sinTheta[$seg];
            $c1 = $cosTheta[$seg + 1];
            $s1 = $sinTheta[$seg + 1];

            $u0 = $seg / $segments;
            $u1 = ($seg + 1) / $segments;

            $n0 = new Vec3($c0, 0.0, $s0);
            $n1 = new Vec3($c1, 0.0, $s1);

            $b0 = new Vec3($radius * $c0, -$hy, $radius * $s0);
            $b1 = new Vec3($radius * $c1, -$hy, $radius * $s1);
            $t0 = new Vec3($radius * $c0, $hy, $radius * $s0);
            $t1 = new Vec3($radius * $c1, $hy, $radius * $s1);

            // side, wound counter-clockwise seen from outside
            $builder->addVertex($b0, $n0, new Vec2($u0, 0.0));
            $builder->addVertex($t0, $n0, new Vec2($u0, 1.0));
            $builder->addVertex($b1, $n1, new Vec2($u1, 0.0));

            $builder->addVertex($b1, $n1, new Vec2($u1, 0.0));
            $builder->addVertex($t0, $n0, new Vec2($u0, 1.0));
            $builder->addVertex($t1, $n1, new Vec2($u1, 1.0));

            if (!$caps) {
                continue;
            }

            // top cap faces up (+Y)
            $builder->addVertex($topCenter, $topNormal, new Vec2(0.5, 0.5));
            $builder->addVertex($t1, $topNormal, new Vec2(0.5 + 0.5 * $c1, 0.5 + 0.5 * $s1));
            $builder->addVertex($t0, $topNormal, new Vec2(0.5 + 0.5 * $c0, 0.5 + 0.5 * $s0));

            // bottom cap faces down (-Y)
            $builder->addVertex($bottomCenter, $bottomNormal, new Vec2(0.5, 0.5));
            $builder->addVertex($b0, $bottomNormal, new Vec2(0.5 + 0.5 * $c0, 0.5 + 0.5 * $s0));
            $builder->addVertex($b1, $bottomNormal, new Vec2(0.5 + 0.5 * $c1, 0.5 + 0.5 * $s1));
        }
    }
}
