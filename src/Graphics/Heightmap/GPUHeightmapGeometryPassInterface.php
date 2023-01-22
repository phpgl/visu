<?php

namespace VISU\Graphics\Heightmap;

use GL\Math\Mat4;
use VISU\ECS\EntitiesInterface;
use VISU\Graphics\RenderTarget;

interface GPUHeightmapGeometryPassInterface
{
    public function renderToHeightmap(EntitiesInterface $entities, RenderTarget $heightRenderTarget, Mat4 $projection) : void;
}