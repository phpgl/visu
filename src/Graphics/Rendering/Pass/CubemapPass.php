<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\CubeVertexArray;
use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\CubemapResource;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\TextureResource;
use VISU\Graphics\ShaderProgram;

class CubemapPass extends RenderPass
{
    /**
     * The name of the cubemap uniform in the shader
     */
    public string $cubemapUniformName = 'u_skybox';

    /**
     * Should the cubemap write to the depth buffer?
     */
    public bool $writeDepth = false;

    /**
     * Constructor 
     */
    public function __construct(
        private RenderTargetResource $renderTargetRes,
        private CubemapResource $cubemapRes,
        private ShaderProgram $shader,
        private ?TextureResource $depthTextureRes = null,
    )
    {
    }

    /**
     * Executes the render pass
     */
    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $pipeline->reads($this, $this->cubemapRes);
        $pipeline->writes($this, $this->renderTargetRes);
        if ($this->depthTextureRes !== null) {
            $pipeline->reads($this, $this->depthTextureRes);
        }
    }

    /**
     * Executes the render pass
     */
    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        $resources->activateRenderTarget($this->renderTargetRes);
        if ($this->depthTextureRes !== null) {
            $resources->useDepthTexture($this->depthTextureRes);
        }

        // fetch camera data
        $cameraData = $data->get(CameraData::class);

        /** @var CubeVertexArray */
        $cubeVA = $resources->cacheStaticResource('cubeva', function(GLState $gl) {
            return new CubeVertexArray($gl);
        });

        $cubeVA->bind();
        $this->shader->use();

        // we need to see the inner faces of the cubemap
        glDisable(GL_CULL_FACE); 
        glEnable(GL_DEPTH_TEST);

        $glCubemap = $resources->getCubemap($this->cubemapRes);
        $this->shader->setUniform1i($this->cubemapUniformName, 0);
        $this->shader->setUniformMat4('u_projection', false, $cameraData->projection);
        $this->shader->setUniformMat4('u_view', false, $cameraData->view);

        // render skybox last and disable depth writes
        if (!$this->writeDepth) {
            glDepthFunc(GL_LEQUAL);
            glDepthMask(false);
        }

        $glCubemap->bind();
        $cubeVA->draw();

        // restore depth state
        if (!$this->writeDepth) {
            glDepthMask(true);
            glDepthFunc(GL_LESS);
        }
    }
}