<?php

namespace VISU\Geo\Primitive;

use GL\Buffer\FloatBuffer;
use GL\Math\{Vec2, Vec3};

/**
 * Generates a UV sphere as a non indexed triangle list.
 *
 * Normals are smooth (the normalized position), which makes the sphere look
 * round under lighting. UVs follow the classic longitude/latitude mapping.
 *
 * The two pole rings are emitted as triangle fans rather than quads, so the
 * mesh contains no degenerate (zero area) triangles at the poles.
 *
 * A sphere shares each grid node between up to six triangles, so the node
 * positions, normals and UVs are computed once into a grid and then referenced
 * by the triangle assembly, instead of recomputing the trigonometry per vertex.
 */
class Sphere
{
    /**
     * Writes a UV sphere into the target buffer.
     *
     * @param VertexLayout $layout The attributes to emit
     * @param FloatBuffer $target The buffer to append the vertices to
     * @param float $radius The radius of the sphere
     * @param int $segments Number of horizontal segments (longitude, >= 3)
     * @param int $rings Number of vertical rings (latitude, >= 2)
     */
    public static function make(
        VertexLayout $layout,
        FloatBuffer $target,
        float $radius = 1.0,
        int $segments = 32,
        int $rings = 16
    ) : void
    {
        $builder = new PrimitiveBuilder($layout, $target);

        $segments = max(3, $segments);
        $rings = max(2, $rings);

        // reserve space up front so the target does not reallocate while filling.
        // the two pole rings are fans (3 verts per segment), the interior rings
        // are quads (6 verts per segment).
        $vertexCount = 2 * $segments * 3 + max(0, $rings - 2) * $segments * 6;
        $target->reserve($target->size() + $vertexCount * $layout->stride());

        // trig tables for the segment angle, one extra entry closes the seam
        $cols = $segments + 1;
        $cosTheta = [];
        $sinTheta = [];
        for ($seg = 0; $seg <= $segments; $seg++) {
            $theta = 2.0 * M_PI * $seg / $segments;
            $cosTheta[$seg] = cos($theta);
            $sinTheta[$seg] = sin($theta);
        }

        // build the grid of distinct nodes: (rings + 1) rows by (segments + 1)
        // columns. each node is computed once and shared by the triangles.
        /** @var array<int, Vec3> $positions */
        $positions = [];
        /** @var array<int, Vec3> $normals */
        $normals = [];
        /** @var array<int, Vec2> $uvs */
        $uvs = [];

        for ($ring = 0; $ring <= $rings; $ring++) {
            $phi = M_PI * $ring / $rings;
            $cosPhi = cos($phi);
            $sinPhi = sin($phi);
            $v = $ring / $rings;

            $rowOffset = $ring * $cols;
            for ($seg = 0; $seg <= $segments; $seg++) {
                // the normal of a sphere is simply its normalized position
                $normal = new Vec3($sinPhi * $cosTheta[$seg], $cosPhi, $sinPhi * $sinTheta[$seg]);

                $index = $rowOffset + $seg;
                $normals[$index] = $normal;
                $positions[$index] = $normal * $radius;
                $uvs[$index] = new Vec2($seg / $segments, $v);
            }
        }

        // assemble the triangle list from the grid nodes. the winding is counter
        // clockwise so the faces point outward.
        for ($ring = 0; $ring < $rings; $ring++) {
            $v0 = $ring / $rings;
            $v1 = ($ring + 1) / $rings;

            $rowTop = $ring * $cols;
            $rowBottom = ($ring + 1) * $cols;

            $isTopCap = $ring === 0;
            $isBottomCap = $ring === $rings - 1;

            for ($seg = 0; $seg < $segments; $seg++) {
                $uMid = ($seg + 0.5) / $segments;

                if ($isTopCap) {
                    // north pole fan: apex + the two corners of the lower ring.
                    // the apex takes the midpoint u to reduce pole distortion.
                    $builder->addVertex($positions[$rowTop + $seg], $normals[$rowTop + $seg], new Vec2($uMid, $v0));
                    $builder->addVertex($positions[$rowBottom + $seg + 1], $normals[$rowBottom + $seg + 1], $uvs[$rowBottom + $seg + 1]);
                    $builder->addVertex($positions[$rowBottom + $seg], $normals[$rowBottom + $seg], $uvs[$rowBottom + $seg]);
                } elseif ($isBottomCap) {
                    // south pole fan: the two corners of the upper ring + apex
                    $builder->addVertex($positions[$rowTop + $seg], $normals[$rowTop + $seg], $uvs[$rowTop + $seg]);
                    $builder->addVertex($positions[$rowTop + $seg + 1], $normals[$rowTop + $seg + 1], $uvs[$rowTop + $seg + 1]);
                    $builder->addVertex($positions[$rowBottom + $seg], $normals[$rowBottom + $seg], new Vec2($uMid, $v1));
                } else {
                    // full quad as two triangles
                    $builder->addVertex($positions[$rowTop + $seg], $normals[$rowTop + $seg], $uvs[$rowTop + $seg]);
                    $builder->addVertex($positions[$rowTop + $seg + 1], $normals[$rowTop + $seg + 1], $uvs[$rowTop + $seg + 1]);
                    $builder->addVertex($positions[$rowBottom + $seg], $normals[$rowBottom + $seg], $uvs[$rowBottom + $seg]);

                    $builder->addVertex($positions[$rowTop + $seg + 1], $normals[$rowTop + $seg + 1], $uvs[$rowTop + $seg + 1]);
                    $builder->addVertex($positions[$rowBottom + $seg + 1], $normals[$rowBottom + $seg + 1], $uvs[$rowBottom + $seg + 1]);
                    $builder->addVertex($positions[$rowBottom + $seg], $normals[$rowBottom + $seg], $uvs[$rowBottom + $seg]);
                }
            }
        }
    }

    /**
     * Writes a unit sphere (radius 1.0) into the target buffer.
     */
    public static function unitSphere(VertexLayout $layout, FloatBuffer $target) : void
    {
        self::make($layout, $target, 1.0);
    }
}
