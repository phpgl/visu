<?php

namespace VISU\Graphics\Rendering\Pass;

use GL\Math\Vec4;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;

class ClearPass extends RenderPass
{
    public function __construct(
        private RenderTargetResource $renderTargetRes
    )
    {
    }   

    /**
     * Executes the render pass
     */
    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $pipeline->writes($this, $this->renderTargetRes);
    }

    /**
     * Executes the render pass
     */
    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        $renderTarget = $resources->getRenderTarget($this->renderTargetRes);
        
        // bind & clear the framebuffer
        $renderTarget->framebuffer()->bind();
        $renderTarget->framebuffer()->clear(GL_COLOR_BUFFER_BIT | GL_DEPTH_BUFFER_BIT);
    }
}
