<?php

namespace VISU\Graphics\Terrain;

use GL\Buffer\FloatBuffer;
use GL\Buffer\UIntBuffer;
use GL\Math\Vec3;
use VISU\Geo\AABB;
use VISU\Graphics\GLState;
use VISU\Graphics\Mesh3D;
use VISU\Graphics\Material;

class HeightmapTerrain
{
    private ?Mesh3D $mesh = null;

    public readonly TerrainData $data;

    public function __construct(
        private GLState $gl,
        TerrainData $data,
    ) {
        $this->data = $data;
    }

    /**
     * Builds the terrain mesh. Call once after construction or when data changes.
     */
    public function buildMesh(?Material $material = null): Mesh3D
    {
        $data = $this->data;
        $w = $data->width;
        $d = $data->depth;

        $material = $material ?? new Material('terrain');

        // compute AABB
        $minY = PHP_FLOAT_MAX;
        $maxY = -PHP_FLOAT_MAX;
        for ($z = 0; $z < $d; $z++) {
            for ($x = 0; $x < $w; $x++) {
                $h = $data->getHeight($x, $z);
                $minY = min($minY, $h);
                $maxY = max($maxY, $h);
            }
        }

        $aabb = new AABB(
            new Vec3(-$data->sizeX * 0.5, $minY, -$data->sizeZ * 0.5),
            new Vec3($data->sizeX * 0.5, $maxY, $data->sizeZ * 0.5),
        );

        $this->mesh = new Mesh3D($this->gl, $material, $aabb);

        // build vertex data: pos(3) + normal(3) + uv(2) + tangent(4) = 12 floats
        $vertices = new FloatBuffer();
        $stepX = $data->sizeX / ($w - 1);
        $stepZ = $data->sizeZ / ($d - 1);

        for ($z = 0; $z < $d; $z++) {
            for ($x = 0; $x < $w; $x++) {
                // position
                $px = -$data->sizeX * 0.5 + $x * $stepX;
                $py = $data->getHeight($x, $z);
                $pz = -$data->sizeZ * 0.5 + $z * $stepZ;
                $vertices->push((float)$px);
                $vertices->push($py);
                $vertices->push((float)$pz);

                // normal from finite differences
                $hL = $data->getHeight($x - 1, $z);
                $hR = $data->getHeight($x + 1, $z);
                $hD = $data->getHeight($x, $z - 1);
                $hU = $data->getHeight($x, $z + 1);
                $nx = $hL - $hR;
                $ny = 2.0 * $stepX;
                $nz = $hD - $hU;
                $len = sqrt($nx * $nx + $ny * $ny + $nz * $nz);
                if ($len > 0.0001) {
                    $nx /= $len;
                    $ny /= $len;
                    $nz /= $len;
                }
                $vertices->push((float)$nx);
                $vertices->push((float)$ny);
                $vertices->push((float)$nz);

                // uv (tiled based on world position)
                $vertices->push($x / (float)($w - 1));
                $vertices->push($z / (float)($d - 1));

                // tangent (along X axis in terrain space)
                $vertices->push(1.0);
                $vertices->push(0.0);
                $vertices->push(0.0);
                $vertices->push(1.0); // handedness
            }
        }

        $this->mesh->uploadVertices($vertices);

        // build index buffer (two triangles per grid cell)
        $indices = new UIntBuffer();
        for ($z = 0; $z < $d - 1; $z++) {
            for ($x = 0; $x < $w - 1; $x++) {
                $topLeft = $z * $w + $x;
                $topRight = $topLeft + 1;
                $bottomLeft = ($z + 1) * $w + $x;
                $bottomRight = $bottomLeft + 1;

                // triangle 1
                $indices->push($topLeft);
                $indices->push($bottomLeft);
                $indices->push($topRight);

                // triangle 2
                $indices->push($topRight);
                $indices->push($bottomLeft);
                $indices->push($bottomRight);
            }
        }

        $this->mesh->uploadIndices($indices);

        return $this->mesh;
    }

    /**
     * Returns the built mesh (null if not yet built)
     */
    public function getMesh(): ?Mesh3D
    {
        return $this->mesh;
    }
}
