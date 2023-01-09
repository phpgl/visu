<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\ECS\EntitiesInterface;
use VISU\Graphics\Rendering\RenderContext;

interface GBufferGeometryPassInterface
{
    public function renderToGBuffer(EntitiesInterface $entities, RenderContext $context, GBufferPassData $gbufferData) : void;
}