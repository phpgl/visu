<?php

namespace VISU\System\VISULowPoly;

use VISU\Geo\AABB;

class LPMesh
{
    /**
     * Constructor
     * 
     * @return void 
     */
    public function __construct(
        public readonly LPMaterial $material,
        public readonly LPVertexBuffer $vertexBuffer,
        public readonly int $vertexOffset,
        public readonly int $vertexCount,
        public readonly AABB $aabb,
    )
    {
    }
}
