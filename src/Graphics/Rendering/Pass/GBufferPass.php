<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;

class GBufferPass extends RenderPass
{
    /**
     * Executes the render pass
     */
    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $cameraData = $data->get(CameraData::class);
        $gbufferData = $data->create(GBufferPassData::class);

        $gbufferData->renderTarget = $pipeline->createRenderTarget('gbuffer', $cameraData->resolutionX, $cameraData->resolutionY);

        $gbufferData->depthTexture = $pipeline->createDepthAttachment($gbufferData->renderTarget);
        $gbufferData->worldSpacePositionTexture = $pipeline->createColorAttachment($gbufferData->renderTarget, 'position');
        $gbufferData->normalTexture = $pipeline->createColorAttachment($gbufferData->renderTarget, 'normal');
        $gbufferData->albedoTexture = $pipeline->createColorAttachment($gbufferData->renderTarget, 'albedo');
    }

    /**
     * Executes the render pass
     */
    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        $gbufferData = $data->get(GBufferPassData::class);

        $renderTarget = $resources->getRenderTarget($gbufferData->renderTarget);
        $renderTarget->preparePass();
        
        // clear the framebuffer
        $renderTarget->framebuffer()->clear(GL_COLOR_BUFFER_BIT | GL_DEPTH_BUFFER_BIT);
    }
}
