<?php

namespace VISU\Graphics\Rendering\Pass;

use GL\Math\Vec3;

class PointLightShadowData
{
    /**
     * Shadow cubemap resolution (per face, pixels)
     */
    public int $resolution = 512;

    /**
     * Number of shadow-casting point lights this frame
     */
    public int $shadowLightCount = 0;

    /**
     * GL cubemap texture IDs for each shadow-casting light
     * @var array<int>
     */
    public array $cubemapTextureIds = [];

    /**
     * Far plane (= range) for each shadow-casting light
     * @var array<float>
     */
    public array $farPlanes = [];

    /**
     * World positions of each shadow-casting light
     * @var array<Vec3>
     */
    public array $lightPositions = [];
}
