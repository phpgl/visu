<?php

namespace VISU\System\VISULowPoly;

use GL\Math\Vec3;

class LPMaterial
{
    /**
     * The material name
     * 
     * @var string
     */
    public string $name;

    /**
     * The material color
     * 
     * @var Vec3
     */
    public Vec3 $color;

    /**
     * The material shininess
     * 
     * @var float
     */
    public float $shininess;

    /**
     * Constructor
     * 
     * @param string $name 
     * @param Vec3 $color 
     * @param float $shininess 
     * @return void 
     */
    public function __construct(
        string $name,
        Vec3 $color,
        float $shininess,
    )
    {
        $this->name = $name;
        $this->color = $color;
        $this->shininess = $shininess;
    }
}
