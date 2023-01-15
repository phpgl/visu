<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\TextureResource;

class DeferredLightPassData
{
    public RenderTargetResource $renderTarget;
    public TextureResource $output;
}
