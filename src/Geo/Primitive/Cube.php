<?php

namespace VISU\Geo\Primitive;

use GL\Buffer\FloatBuffer;
use GL\Math\{Vec2, Vec3};

/**
 * Generates an axis aligned box centered at the origin as a non indexed
 * triangle list.
 *
 * Normals are flat (constant per face) so the box keeps hard edges. Every face
 * is mapped with UVs spanning the full 0..1 range.
 */
class Cube
{
    /**
     * Writes a box into the target buffer.
     *
     * @param VertexLayout $layout The attributes to emit
     * @param FloatBuffer $target The buffer to append the vertices to
     * @param float $width Size along the x axis
     * @param float $height Size along the y axis
     * @param float $depth Size along the z axis
     */
    public static function make(
        VertexLayout $layout,
        FloatBuffer $target,
        float $width = 1.0,
        float $height = 1.0,
        float $depth = 1.0
    ) : void
    {
        $builder = new PrimitiveBuilder($layout, $target);

        $hx = $width * 0.5;
        $hy = $height * 0.5;
        $hz = $depth * 0.5;

        // each face: [normal, center, uDir, vDir] where uDir x vDir points along
        // the outward normal so the triangle winding stays counter-clockwise
        $faces = [
            [new Vec3(1.0, 0.0, 0.0),  new Vec3($hx, 0.0, 0.0),  new Vec3(0.0, 0.0, -$hz), new Vec3(0.0, $hy, 0.0)],
            [new Vec3(-1.0, 0.0, 0.0), new Vec3(-$hx, 0.0, 0.0), new Vec3(0.0, 0.0, $hz),  new Vec3(0.0, $hy, 0.0)],
            [new Vec3(0.0, 1.0, 0.0),  new Vec3(0.0, $hy, 0.0),  new Vec3($hx, 0.0, 0.0),  new Vec3(0.0, 0.0, -$hz)],
            [new Vec3(0.0, -1.0, 0.0), new Vec3(0.0, -$hy, 0.0), new Vec3($hx, 0.0, 0.0),  new Vec3(0.0, 0.0, $hz)],
            [new Vec3(0.0, 0.0, 1.0),  new Vec3(0.0, 0.0, $hz),  new Vec3($hx, 0.0, 0.0),  new Vec3(0.0, $hy, 0.0)],
            [new Vec3(0.0, 0.0, -1.0), new Vec3(0.0, 0.0, -$hz), new Vec3(-$hx, 0.0, 0.0), new Vec3(0.0, $hy, 0.0)],
        ];

        foreach ($faces as [$normal, $center, $uDir, $vDir]) {
            $bl = $center - $uDir - $vDir;
            $br = $center + $uDir - $vDir;
            $tr = $center + $uDir + $vDir;
            $tl = $center - $uDir + $vDir;

            $builder->addVertex($bl, $normal, new Vec2(0.0, 0.0));
            $builder->addVertex($br, $normal, new Vec2(1.0, 0.0));
            $builder->addVertex($tr, $normal, new Vec2(1.0, 1.0));

            $builder->addVertex($bl, $normal, new Vec2(0.0, 0.0));
            $builder->addVertex($tr, $normal, new Vec2(1.0, 1.0));
            $builder->addVertex($tl, $normal, new Vec2(0.0, 1.0));
        }
    }

    /**
     * Writes a 1x1x1 cube into the target buffer.
     */
    public static function standardCube(VertexLayout $layout, FloatBuffer $target) : void
    {
        self::make($layout, $target, 1.0, 1.0, 1.0);
    }
}
