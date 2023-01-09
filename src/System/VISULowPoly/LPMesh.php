<?php

namespace VISU\System\VISULowPoly;

class LPMesh
{
    /**
     * Constructor
     * 
     * @return void 
     */
    public function __construct(
        public readonly  LPMaterial $material,
        public readonly  LPVertexBuffer $vertexBuffer,
        public readonly int $vertexOffset,
        public readonly int $vertexCount,
    )
    {
    }
}
