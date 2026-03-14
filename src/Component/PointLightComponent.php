<?php

namespace VISU\Component;

use GL\Math\Vec3;

class PointLightComponent
{
    /**
     * Light color
     */
    public Vec3 $color;

    /**
     * Light intensity
     */
    public float $intensity;

    /**
     * Maximum range of the light (used for attenuation and culling)
     */
    public float $range;

    /**
     * Constant attenuation factor
     */
    public float $constantAttenuation = 1.0;

    /**
     * Linear attenuation factor
     */
    public float $linearAttenuation = 0.09;

    /**
     * Quadratic attenuation factor
     */
    public float $quadraticAttenuation = 0.032;

    /**
     * Whether this light casts shadows
     */
    public bool $castsShadows = false;

    public function __construct(
        ?Vec3 $color = null,
        float $intensity = 1.0,
        float $range = 20.0,
    ) {
        $this->color = $color ?? new Vec3(1.0, 1.0, 1.0);
        $this->intensity = $intensity;
        $this->range = $range;
    }

    /**
     * Sets physically-based attenuation from the range value
     */
    public function setAttenuationFromRange(): void
    {
        $this->constantAttenuation = 1.0;
        $this->linearAttenuation = 4.5 / $this->range;
        $this->quadraticAttenuation = 75.0 / ($this->range * $this->range);
    }
}
