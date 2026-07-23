<?php

namespace VISU\Geo\Primitive;

use GL\Buffer\FloatBuffer;
use GL\Math\{Vec2, Vec3};

/**
 * Generates a cone aligned to the Y axis, centered at the origin, as a non
 * indexed triangle list. The base sits at the bottom and the apex at the top.
 *
 * Side normals are tilted to follow the slope of the cone; the optional bottom
 * cap uses a flat normal.
 */
class Cone
{
    /**
     * Writes a cone into the target buffer.
     *
     * @param VertexLayout $layout The attributes to emit
     * @param FloatBuffer $target The buffer to append the vertices to
     * @param float $radius The radius of the base
     * @param float $height The height along the y axis
     * @param int $segments Number of segments around the base (>= 3)
     * @param bool $cap Whether to generate the bottom cap
     */
    public static function make(
        VertexLayout $layout,
        FloatBuffer $target,
        float $radius = 1.0,
        float $height = 1.0,
        int $segments = 32,
        bool $cap = true
    ) : void
    {
        $builder = new PrimitiveBuilder($layout, $target);

        $segments = max(3, $segments);
        $hy = $height * 0.5;
        $apex = new Vec3(0.0, $hy, 0.0);

        // reserve space up front so the target does not reallocate while filling:
        // 3 side verts per segment, plus 3 per segment for the cap when requested
        $vertexCount = $segments * 3 + ($cap ? $segments * 3 : 0);
        $target->reserve($target->size() + $vertexCount * $layout->stride());

        // trig tables for the segment angle. the boundary table has one extra
        // entry to close the seam, the mid table samples each segment center.
        $cosTheta = [];
        $sinTheta = [];
        for ($seg = 0; $seg <= $segments; $seg++) {
            $theta = 2.0 * M_PI * $seg / $segments;
            $cosTheta[$seg] = cos($theta);
            $sinTheta[$seg] = sin($theta);
        }
        $cosMid = [];
        $sinMid = [];
        for ($seg = 0; $seg < $segments; $seg++) {
            $theta = 2.0 * M_PI * ($seg + 0.5) / $segments;
            $cosMid[$seg] = cos($theta);
            $sinMid[$seg] = sin($theta);
        }

        $bottomNormal = new Vec3(0.0, -1.0, 0.0);
        $bottomCenter = new Vec3(0.0, -$hy, 0.0);

        for ($seg = 0; $seg < $segments; $seg++) {
            $c0 = $cosTheta[$seg];
            $s0 = $sinTheta[$seg];
            $c1 = $cosTheta[$seg + 1];
            $s1 = $sinTheta[$seg + 1];

            $u0 = $seg / $segments;
            $u1 = ($seg + 1) / $segments;
            $uMid = ($u0 + $u1) * 0.5;

            $b0 = new Vec3($radius * $c0, -$hy, $radius * $s0);
            $b1 = new Vec3($radius * $c1, -$hy, $radius * $s1);

            // the side normal tilts with the slope of the cone
            $n0 = self::sideNormal($c0, $s0, $radius, $height);
            $n1 = self::sideNormal($c1, $s1, $radius, $height);
            $nApex = self::sideNormal($cosMid[$seg], $sinMid[$seg], $radius, $height);

            // side, wound counter-clockwise seen from outside
            $builder->addVertex($b0, $n0, new Vec2($u0, 0.0));
            $builder->addVertex($apex, $nApex, new Vec2($uMid, 1.0));
            $builder->addVertex($b1, $n1, new Vec2($u1, 0.0));

            if (!$cap) {
                continue;
            }

            // bottom cap faces down (-Y)
            $builder->addVertex($bottomCenter, $bottomNormal, new Vec2(0.5, 0.5));
            $builder->addVertex($b0, $bottomNormal, new Vec2(0.5 + 0.5 * $c0, 0.5 + 0.5 * $s0));
            $builder->addVertex($b1, $bottomNormal, new Vec2(0.5 + 0.5 * $c1, 0.5 + 0.5 * $s1));
        }
    }

    /**
     * Computes the outward side normal from the segment angle's cosine and sine,
     * tilted by the slope defined by the base radius and the cone height.
     */
    private static function sideNormal(float $cosTheta, float $sinTheta, float $radius, float $height) : Vec3
    {
        $normal = new Vec3($height * $cosTheta, $radius, $height * $sinTheta);
        $normal->normalize();

        return $normal;
    }
}
