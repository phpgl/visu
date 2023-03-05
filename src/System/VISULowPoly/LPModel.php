<?php

namespace VISU\System\VISULowPoly;

use GL\Math\Vec3;
use VISU\Geo\AABB;

class LPModel
{
    /**
     * The model name
     * 
     * @var string
     */
    public string $name;

    /**
     * An array of meshes the model is composed of
     * 
     * @var array<LPMesh>
     */
    public array $meshes;

    /**
     * The axis aligned bounding box of the model (all meshes)
     */
    public AABB $aabb;

    /**
     * Constructor
     * 
     * @param string $name 
     * @param array<LPMesh> $meshes
     * @return void 
     */
    public function __construct(
        string $name,
        array $meshes = [],
    )
    {
        $this->name = $name;
        $this->meshes = $meshes;
    }

    /**
     * Recalculates the AABB of the model based on the meshes
     */
    public function recalculateAABB() : void
    {
        $this->aabb = new AABB(new Vec3(0, 0, 0), new Vec3(0, 0, 0));
        foreach ($this->meshes as $mesh) {
            $this->aabb->extend($mesh->aabb);
        }
    }
}
