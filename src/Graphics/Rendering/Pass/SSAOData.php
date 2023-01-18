<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\TextureResource;

class SSAOData
{
    public RenderTargetResource $ssaoTarget;
    public TextureResource $ssaoTexture;

    public RenderTargetResource $blurTarget;
    public TextureResource $blurTexture;
}
