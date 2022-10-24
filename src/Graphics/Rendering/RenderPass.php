<?php

namespace VISU\Graphics\Rendering;

abstract class RenderPass
{
    /**
     * Executes the render pass
     */
    abstract public function setup(RenderPipeline $pipeline): void;

    /**
     * Executes the render pass
     */
    abstract public function execute(enderPipeline $pipeline): void;
}
