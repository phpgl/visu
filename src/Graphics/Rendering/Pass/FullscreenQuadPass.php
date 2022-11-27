<?php

namespace VISU\Graphics\Rendering\Pass;

use GL\Math\Vec4;
use VISU\Graphics\GLState;
use VISU\Graphics\QuadVertexArray;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\TextureResource;

class FullscreenQuadPass extends RenderPass
{
    public function __construct(
        private TextureResource $appliedTexture
    )
    {
    }

    /**
     * Executes the render pass
     */
    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        // $pipeline->reads($this->appliedTexture);
    }

    /**
     * Executes the render pass
     */
    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        /** @var QuadVertexArray */
        $quadVA = $resources->cacheStaticResource('quadva', function(GLState $gl) {
            return new QuadVertexArray($gl);
        });

        $quadVA->bind();

        // 
        
        // bind & clear the framebuffer
        $renderTarget->framebuffer()->bind();
        $renderTarget->framebuffer()->clear(GL_COLOR_BUFFER_BIT | GL_DEPTH_BUFFER_BIT);
    }
}
