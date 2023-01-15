<?php

namespace VISU\Component;

use GL\Math\Vec3;

class DirectionalLightComponent
{
    /**
     * The direction the light is pointing at
     */
    public Vec3 $direction;

    /**
     * The color of the light
     */
    public Vec3 $color;

    /**
     * The intensity of the light
     */
    public float $intensity;

    /**
     * Constructor
     */
    public function __construct(
        ?Vec3 $direction = null,
        ?Vec3 $color = null,
        float $intensity = 1.0
    )
    {
        if ($direction === null) {
            $direction = new Vec3(0.0, -1.0, 0.0);
        }
        if ($color === null) {
            $color = new Vec3(1.0, 1.0, 1.0);
        }

        $this->direction = $direction;
        $this->color = $color;
        $this->intensity = $intensity;
    }
}