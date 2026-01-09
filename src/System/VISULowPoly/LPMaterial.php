<?php

namespace VISU\System\VISULowPoly;

use GL\Math\Vec3;

class LPMaterial
{
    /**
     * Static material counter
     */
    private static int $materialCounter = 0;

    /**
     * The material name
     */
    public string $name;

    /**
     * Global unique material ID
     */
    public int $id;

    /**
     * The material color
     */
    public Vec3 $color;

    /**
     * The material roughness
     */
    public float $roughness;

    /**
     * The material metallic factor
     */
    public float $metallic;

    /**
     * Emissive color of the material 
     */
    public ?Vec3 $emissive = null;

    /**
     * The handle of the static geometry draw call assembler entry for this material
     */
    public ?int $staticDCAHandle = null;

    /**
     * The handle of the dynamic geometry draw call assembler entry for this material
     */
    public ?int $dynamicDCAHandle = null;

    /**
     * Constructor
     * 
     * @param string $name 
     * @param Vec3 $color 
     * @param float $roughness 
     * @return void 
     */
    public function __construct(
        string $name,
        Vec3 $color,
        float $roughness,
        float $metallic = 0.0,
    )
    {
        $this->id = self::$materialCounter++;
        $this->name = $name;
        $this->color = $color;
        $this->roughness = $roughness;
        $this->metallic = $metallic;
    }
}
