<?php

namespace VISU\System\VISULowPoly;

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
}
