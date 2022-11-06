<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\ShaderProgram;

class GBufferPass extends RenderPass
{
    private ShaderProgram $gbufferShader;

    public function __construct(ShaderRegistry $shaders)
    {
        $this->gbufferShader = $shaders->requestShader("gbuffer");
    }   

    /**
     * Executes the render pass
     */
    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $gbufferData = $data->create(GBufferPassData::class);
        $frameContext = $data->get(FrameContext::class);

        $gbufferData->depthTexture = $pipeline->createTexture('gbuffer_depth', $frameContext->resolutionWidth, $frameContext->resolutionHeight);
        $pipeline->writes($gbufferData->depthTexture);

        $gbufferData->albedoTexture = $pipeline->createTexture('gbuffer_albedo', $frameContext->resolutionWidth, $frameContext->resolutionHeight);
        $pipeline->writes($gbufferData->albedoTexture);

        $gbufferData->renderTarget = $pipeline->createRenderTarget('gbuffer', $frameContext->resolutionWidth, $frameContext->resolutionHeight);
        $gbufferData->renderTarget->attachDepthTexture($gbufferData->depthTexture);
        $gbufferData->renderTarget->attachColorTexture($gbufferData->albedoTexture);
    }

    /**
     * Executes the render pass
     */
    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        $gbufferData = $data->get(GBufferPassData::class);
        $frameContext = $data->get(FrameContext::class);

        $renderTarget = $resources->getRenderTarget($gbufferData->renderTarget);
        $renderTarget->preparePass();
        
        // clear the framebuffer
        $renderTarget->framebuffer()->clear(GL_COLOR_BUFFER_BIT | GL_DEPTH_BUFFER_BIT);

        // get the to be rendered geometry
        $this->gbufferShader->use();

        // apply base uniforms
        $this->gbufferShader->setUniformMatrix4f('projection', false, $frameContext->renderCamera->getProjectionMatrix());
        $this->gbufferShader->setUniformMatrix4f('view', false, $frameContext->renderCamera->getViewMatrix());
    }
}
