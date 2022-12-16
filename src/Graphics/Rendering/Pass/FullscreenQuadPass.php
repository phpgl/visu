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
        private ShaderProgram $shader,
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
        $this->shader->use();

        $glTexture = $resources->getTextureID($this->appliedTexture);
        $this->shader->setUniform1i($this->textureUniformName, 0);
        glActiveTexture(GL_TEXTURE0);
        glBindTexture(GL_TEXTURE_2D, $glTexture);

        var_dump($glTexture); die;

        $quadVA->draw();
    }
}
