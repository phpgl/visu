<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\TextureResource;

class GBufferPassData
{
    public RenderTargetResource $renderTarget;
    public TextureResource $depthTexture;
    public TextureResource $worldSpacePositionTexture;
    public TextureResource $viewSpacePositionTexture;
    public TextureResource $normalTexture; 
    public TextureResource $albedoTexture;
}
