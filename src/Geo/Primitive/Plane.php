<?php

namespace VISU\Geo\Primitive;

use GL\Buffer\FloatBuffer;
use GL\Math\{Vec2, Vec3};

/**
 * Generates a flat, optionally subdivided plane on the XZ axis, centered at the
 * origin, as a non indexed triangle list.
 *
 * The plane faces up (normal `(0, 1, 0)`) and its UVs span the full 0..1 range.
 */
class Plane
{
    /**
     * Writes a plane into the target buffer.
     *
     * @param VertexLayout $layout The attributes to emit
     * @param FloatBuffer $target The buffer to append the vertices to
     * @param float $width Size along the x axis
     * @param float $depth Size along the z axis
     * @param int $subdivisions Number of cells per axis (>= 1)
     */
    public static function make(
        VertexLayout $layout,
        FloatBuffer $target,
        float $width = 1.0,
        float $depth = 1.0,
        int $subdivisions = 1
    ) : void
    {
        $builder = new PrimitiveBuilder($layout, $target);

        $subdivisions = max(1, $subdivisions);
        $normal = new Vec3(0.0, 1.0, 0.0);

        $hw = $width * 0.5;
        $hd = $depth * 0.5;

        for ($ix = 0; $ix < $subdivisions; $ix++) {
            $fx0 = $ix / $subdivisions;
            $fx1 = ($ix + 1) / $subdivisions;

            for ($iz = 0; $iz < $subdivisions; $iz++) {
                $fz0 = $iz / $subdivisions;
                $fz1 = ($iz + 1) / $subdivisions;

                $x0 = -$hw + $fx0 * $width;
                $x1 = -$hw + $fx1 * $width;
                $z0 = -$hd + $fz0 * $depth;
                $z1 = -$hd + $fz1 * $depth;

                $p00 = new Vec3($x0, 0.0, $z0);
                $p01 = new Vec3($x0, 0.0, $z1);
                $p10 = new Vec3($x1, 0.0, $z0);
                $p11 = new Vec3($x1, 0.0, $z1);

                // counter-clockwise winding when viewed from above (+Y)
                $builder->addVertex($p00, $normal, new Vec2($fx0, $fz0));
                $builder->addVertex($p01, $normal, new Vec2($fx0, $fz1));
                $builder->addVertex($p11, $normal, new Vec2($fx1, $fz1));

                $builder->addVertex($p00, $normal, new Vec2($fx0, $fz0));
                $builder->addVertex($p11, $normal, new Vec2($fx1, $fz1));
                $builder->addVertex($p10, $normal, new Vec2($fx1, $fz0));
            }
        }
    }

    /**
     * Writes a 1x1 plane into the target buffer.
     */
    public static function unitPlane(VertexLayout $layout, FloatBuffer $target) : void
    {
        self::make($layout, $target, 1.0, 1.0, 1);
    }
}
