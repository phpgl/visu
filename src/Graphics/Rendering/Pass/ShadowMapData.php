<?php

namespace VISU\Graphics\Rendering\Pass;

use GL\Math\Mat4;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\TextureResource;

class ShadowMapData
{
    /**
     * Number of shadow cascades
     */
    public int $cascadeCount = 4;

    /**
     * Shadow map resolution (per cascade)
     */
    public int $resolution = 2048;

    /**
     * Render targets for each cascade
     * @var array<RenderTargetResource>
     */
    public array $renderTargets = [];

    /**
     * Depth textures for each cascade
     * @var array<TextureResource>
     */
    public array $depthTextures = [];

    /**
     * Light-space matrices for each cascade (projection * view from light)
     * @var array<Mat4>
     */
    public array $lightSpaceMatrices = [];

    /**
     * Cascade split distances (in view space, positive values)
     * @var array<float>
     */
    public array $cascadeSplits = [];
}
