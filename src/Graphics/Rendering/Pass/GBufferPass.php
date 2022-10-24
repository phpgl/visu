<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\Graphics\Rendering\RenderPass;
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

        $gbufferData->depthTexture = $pipeline->createTexture('gbuffer_depth', 1280, 720);
        $pipeline->writes($gbufferData->depthTexture);

        $gbufferData->albedoTexture = $pipeline->createTexture('gbuffer_albedo', 1280, 720);
        $pipeline->writes($gbufferData->albedoTexture);



        $gbufferData->fb = $pipeline->createFramebuffer('gbuffer');
        $gbufferData->fb->attachDepthTexture($gbufferData->depthTexture);
        $gbufferData->fb->attachColorTexture($gbufferData->albedoTexture);
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

        // get the to be rendered geometry
        $gbufferShader->use();
        $gbufferData->
    }
}
