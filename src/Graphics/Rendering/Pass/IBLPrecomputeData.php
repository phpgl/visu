<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\Graphics\Rendering\Resource\CubemapResource;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\TextureResource;

class IBLPrecomputeData
{
    public RenderTargetResource $irradianceRenderTarget;
    public CubemapResource $irradianceCubemap;
    
    public RenderTargetResource $prefilterRenderTarget;
    public CubemapResource $prefilterCubemap;

    /**
     * Number of mip levels generated for the prefilter cubemap
     * This is used by the light pass to properly sample the prefilter map
     */
    public int $prefilterMipLevels = 5;

    public RenderTargetResource $brdfLutRenderTarget;
    public TextureResource $brdfLut;

    /**
     * Reference to the cache used by the IBL precompute pass
     */
    public ?IBLCache $cache = null;
}