<?php

namespace VISU\Graphics\Rendering\Pass;

use GL\Math\Vec3;
use VISU\Component\DirectionalLightComponent;
use VISU\D3D;
use VISU\Graphics\GLState;
use VISU\Graphics\QuadVertexArray;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\ShaderProgram;

class DeferredLightPass extends RenderPass
{
    /**
     * Constructor
     */
    public function __construct(
        private ShaderProgram $lightingShader,
        private DirectionalLightComponent $sun
    )
    {
    }

    /**
     * Executes the render pass
     */
    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $gbufferData = $data->get(GBufferPassData::class);
        $lightpassData = $data->create(DeferredLightPassData::class);

        $pipeline->reads($this, $gbufferData->albedoTexture);
        $pipeline->reads($this, $gbufferData->normalTexture);
        $pipeline->reads($this, $gbufferData->worldSpacePositionTexture);

        // create light pass target with the same size as the gbuffer
        $lightpassData->renderTarget = $pipeline->createRenderTarget('lightpass', $gbufferData->renderTarget->width, $gbufferData->renderTarget->height);
        $lightpassData->output = $pipeline->createColorAttachment($lightpassData->renderTarget, 'lightpass_output');
    }

    /**
     * Executes the render pass
     */
    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        $gbufferData = $data->get(GBufferPassData::class);
        $cameraData = $data->get(CameraData::class);
        $lightpassData = $data->get(DeferredLightPassData::class);

        $resources->activateRenderTarget($lightpassData->renderTarget);

        /** @var QuadVertexArray */
        $quadVA = $resources->cacheStaticResource('quadva', function(GLState $gl) {
            return new QuadVertexArray($gl);
        });

        // prepare the shader
        $this->lightingShader->use();
        $this->lightingShader->setUniformVec3('camera_position', $cameraData->renderCamera->transform->position);
        $this->lightingShader->setUniform2f('camera_resolution', $cameraData->resolutionX, $cameraData->resolutionY);

        // set sun properties
        $this->sun->direction->x = -1.0;
        $this->sun->direction->y = 1.0;
        $this->sun->intensity = 2.0;
        $this->sun->direction->normalize();

        D3D::ray(new Vec3(0.0), $this->sun->direction, D3D::$colorYellow, 200.0);
        D3D::cross(new Vec3(0.0), D3D::$colorYellow, 50.0);


        $this->lightingShader->setUniformVec3('sun_direction', $this->sun->direction);
        $this->lightingShader->setUniformVec3('sun_color', $this->sun->color);
        $this->lightingShader->setUniform1f('sun_intensity', $this->sun->intensity);

        // bind the gbuffer textures
        foreach([
            [$gbufferData->worldSpacePositionTexture, 'position'],
            [$gbufferData->normalTexture, 'normal'],
            [$gbufferData->depthTexture, 'depth'],
            [$gbufferData->albedoTexture, 'albedo']
        ] as $i => $tuple) {
            list($texture, $name) = $tuple;
            $glTexture = $resources->getTexture($texture);
            $glTexture->bind(GL_TEXTURE0 + $i);
            $this->lightingShader->setUniform1i('gbuffer_' . $name, $i);
        }

        glDisable(GL_DEPTH_TEST);
        glEnable(GL_CULL_FACE);

        $quadVA->bind();
        $quadVA->draw();
    }
}
