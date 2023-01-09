<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\Graphics\GLState;
use VISU\Graphics\QuadVertexArray;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\TextureResource;
use VISU\Graphics\ShaderProgram;

class FullscreenQuadPass extends RenderPass
{
    /**
     * The name of the texture unform in the shader
     */
    public string $textureUniformName = 'u_texture';

    /**
     * Constructor 
     * 
     * @return void 
     */
    public function __construct(
        private RenderTargetResource $renderTargetRes,
        private TextureResource $appliedTexture,
        private ShaderProgram $shader,
    )
    {
    }

    /**
     * Executes the render pass
     */
    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $pipeline->reads($this, $this->appliedTexture);
        $pipeline->writes($this, $this->renderTargetRes);
    }

    /**
     * Executes the render pass
     */
    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        $resources->activateRenderTarget($this->renderTargetRes);

        /** @var QuadVertexArray */
        $quadVA = $resources->cacheStaticResource('quadva', function(GLState $gl) {
            return new QuadVertexArray($gl);
        });

        $quadVA->bind();
        $this->shader->use();

        $glTexture = $resources->getTexture($this->appliedTexture);
        $this->shader->setUniform1i($this->textureUniformName, 0);

        glDisable(GL_DEPTH_TEST);

        $glTexture->bind();
        $quadVA->draw();
    }
}
