<?php

namespace VISU\System\VISULowPoly;

use VISU\Geo\AABB;

class LPMesh
{
    /**
     * The handle of the static geometry draw call assembler entry for this mesh
     */
    public ?int $staticDCAHandle = null;
     
    /**
     * The handle of the dynamic geometry draw call assembler entry for this mesh
     */
    public ?int $dynamicDCAHandle = null;

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
