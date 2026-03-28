<?php

namespace VISU\System;

use VISU\Component\DirectionalLightComponent;
use VISU\Component\MeshRendererComponent;
use VISU\Component\PointLightComponent;
use VISU\Component\SpotLightComponent;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\Transform;
use VISU\Graphics\GLState;
use VISU\Graphics\Material;
use VISU\Graphics\Mesh3D;
use VISU\Graphics\ModelCollection;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\Pass\DeferredLightPassData;
use VISU\Graphics\Rendering\Pass\PBRDeferredLightPass;
use VISU\Graphics\Rendering\Pass\PBRGBufferData;
use VISU\Graphics\Rendering\Pass\PBRGBufferPass;
use VISU\Graphics\Rendering\Pass\GBufferPassData;
use VISU\Graphics\Rendering\Pass\PointLightShadowPass;
use VISU\Graphics\Rendering\Pass\ShadowMapPass;
use VISU\Graphics\Rendering\Pass\SSAOData;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\PostProcessStack;
use VISU\Graphics\Rendering\Renderer\FullscreenDebugDepthRenderer;
use VISU\Graphics\Rendering\Renderer\FullscreenTextureRenderer;
use VISU\Graphics\Rendering\Renderer\SSAORenderer;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\ShaderCollection;
use VISU\Graphics\ShaderProgram;

class Rendering3DSystem implements SystemInterface
{
    const DEBUG_MODE_NONE = 0;
    const DEBUG_MODE_POSITION = 1;
    const DEBUG_MODE_NORMALS = 2;
    const DEBUG_MODE_DEPTH = 3;
    const DEBUG_MODE_ALBEDO = 4;
    const DEBUG_MODE_SSAO = 5;
    const DEBUG_MODE_METALLIC_ROUGHNESS = 6;
    const DEBUG_MODE_EMISSIVE = 7;

    public int $debugMode = self::DEBUG_MODE_NONE;

    /**
     * Enable/disable shadow mapping
     */
    public bool $shadowsEnabled = true;

    /**
     * Shadow map resolution per cascade (pixels)
     */
    public int $shadowResolution = 2048;

    /**
     * Number of shadow cascades (1-4)
     */
    public int $shadowCascadeCount = 4;

    /**
     * Enable/disable point light cubemap shadows
     */
    public bool $pointShadowsEnabled = true;

    /**
     * Point light shadow cubemap resolution per face (pixels)
     */
    public int $pointShadowResolution = 512;

    /**
     * Post-processing stack (Bloom, DoF, Motion Blur)
     */
    public ?PostProcessStack $postProcessStack = null;

    private ?RenderTargetResource $currentRenderTargetRes = null;

    private FullscreenTextureRenderer $fullscreenRenderer;
    private FullscreenDebugDepthRenderer $fullscreenDebugDepthRenderer;
    private SSAORenderer $ssaoRenderer;

    private ShaderProgram $geometryShader;
    private ShaderProgram $lightingShader;
    private ShaderProgram $shadowDepthShader;
    private ShaderProgram $pointShadowDepthShader;

    public function __construct(
        private GLState $gl,
        private ShaderCollection $shaders,
        private ModelCollection $modelCollection,
    ) {
        $this->fullscreenRenderer = new FullscreenTextureRenderer($this->gl);
        $this->fullscreenDebugDepthRenderer = new FullscreenDebugDepthRenderer($this->gl);
        $this->ssaoRenderer = new SSAORenderer($this->gl, $this->shaders);

        $this->geometryShader = $this->shaders->get('visu/pbr/geometry');
        $this->lightingShader = $this->shaders->get('visu/pbr/lightpass');
        $this->shadowDepthShader = $this->shaders->get('visu/pbr/shadow_depth');
        $this->pointShadowDepthShader = $this->shaders->get('visu/pbr/point_shadow_depth');
    }

    public function register(EntitiesInterface $entities): void
    {
        $entities->registerComponent(MeshRendererComponent::class);
        $entities->registerComponent(PointLightComponent::class);
        $entities->registerComponent(SpotLightComponent::class);
        $entities->registerComponent(Transform::class);

        $entities->setSingleton(new DirectionalLightComponent);
    }

    public function unregister(EntitiesInterface $entities): void
    {
    }

    public function update(EntitiesInterface $entities): void
    {
    }

    public function setRenderTarget(RenderTargetResource $renderTargetRes): void
    {
        $this->currentRenderTargetRes = $renderTargetRes;
    }

    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
        if ($this->currentRenderTargetRes === null) {
            throw new \RuntimeException('No render target set, call setRenderTarget() before render()');
        }

        // PBR GBuffer pass (creates standard + metallic/roughness + emissive attachments)
        $context->pipeline->addPass(new PBRGBufferPass);

        $gbuffer = $context->data->get(GBufferPassData::class);
        $pbrGbuffer = $context->data->get(PBRGBufferData::class);

        // geometry pass — render all MeshRendererComponent entities
        $context->pipeline->addPass(new CallbackPass(
            'PBRGeometry',
            function (RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data) use ($gbuffer) {
                $pipeline->writes($pass, $gbuffer->renderTarget);
            },
            function (PipelineContainer $data, PipelineResources $resources) use ($entities) {
                $cameraData = $data->get(CameraData::class);

                $this->geometryShader->use();
                $this->geometryShader->setUniformMatrix4f('projection', false, $cameraData->projection);
                $this->geometryShader->setUniformMatrix4f('view', false, $cameraData->view);
                glEnable(GL_DEPTH_TEST);

                foreach ($entities->view(MeshRendererComponent::class) as $entity => $renderer) {
                    $transform = $entities->get($entity, Transform::class);
                    $this->geometryShader->setUniformMatrix4f('model', false, $transform->getWorldMatrix($entities));

                    if (!$this->modelCollection->has($renderer->modelIdentifier)) {
                        continue;
                    }

                    $model = $this->modelCollection->get($renderer->modelIdentifier);

                    foreach ($model->meshes as $mesh) {
                        $material = $renderer->materialOverride ?? $mesh->material;
                        $this->bindMaterial($material);
                        $mesh->draw();
                    }
                }
            }
        ));

        // capture render target (non-null guaranteed by check above) and reset
        assert($this->currentRenderTargetRes !== null);
        $renderTarget = $this->currentRenderTargetRes;
        $this->currentRenderTargetRes = null;

        // debug modes
        if ($this->debugMode === self::DEBUG_MODE_NORMALS) {
            $this->fullscreenRenderer->attachPass($context->pipeline, $renderTarget, $gbuffer->normalTexture);
            return;
        }
        if ($this->debugMode === self::DEBUG_MODE_POSITION) {
            $this->fullscreenRenderer->attachPass($context->pipeline, $renderTarget, $gbuffer->worldSpacePositionTexture);
            return;
        }
        if ($this->debugMode === self::DEBUG_MODE_DEPTH) {
            $this->fullscreenDebugDepthRenderer->attachPass($context->pipeline, $renderTarget, $gbuffer->depthTexture);
            return;
        }
        if ($this->debugMode === self::DEBUG_MODE_ALBEDO) {
            $this->fullscreenRenderer->attachPass($context->pipeline, $renderTarget, $gbuffer->albedoTexture);
            return;
        }
        if ($this->debugMode === self::DEBUG_MODE_METALLIC_ROUGHNESS) {
            $this->fullscreenRenderer->attachPass($context->pipeline, $renderTarget, $pbrGbuffer->metallicRoughnessTexture);
            return;
        }
        if ($this->debugMode === self::DEBUG_MODE_EMISSIVE) {
            $this->fullscreenRenderer->attachPass($context->pipeline, $renderTarget, $pbrGbuffer->emissiveTexture);
            return;
        }

        // Shadow map pass (before SSAO and lighting)
        if ($this->shadowsEnabled) {
            $context->pipeline->addPass(new ShadowMapPass(
                $this->shadowDepthShader,
                $entities->getSingleton(DirectionalLightComponent::class),
                $entities,
                $this->modelCollection,
                cascadeCount: $this->shadowCascadeCount,
                resolution: $this->shadowResolution,
            ));
        }

        // Point light cubemap shadow pass
        if ($this->pointShadowsEnabled) {
            $context->pipeline->addPass(new PointLightShadowPass(
                $this->pointShadowDepthShader,
                $entities,
                $this->modelCollection,
                resolution: $this->pointShadowResolution,
            ));
        }

        // SSAO
        $this->ssaoRenderer->attachPass($context->pipeline);
        $ssaoData = $context->data->get(SSAOData::class);

        if ($this->debugMode === self::DEBUG_MODE_SSAO) {
            $this->fullscreenRenderer->attachPass($context->pipeline, $renderTarget, $ssaoData->blurTexture, true);
            return;
        }

        // PBR light pass (directional + point lights + shadows)
        $context->pipeline->addPass(new PBRDeferredLightPass(
            $this->lightingShader,
            $entities->getSingleton(DirectionalLightComponent::class),
            $entities,
        ));

        // post-processing chain
        $lightpass = $context->data->get(DeferredLightPassData::class);
        $finalOutput = $lightpass->output;

        if ($this->postProcessStack !== null && $this->postProcessStack->hasActiveEffects()) {
            $finalOutput = $this->postProcessStack->attachPasses(
                $context->pipeline,
                $context->data,
                $finalOutput,
            );
        }

        // copy to final render target
        $this->fullscreenRenderer->attachPass($context->pipeline, $renderTarget, $finalOutput);
    }

    /**
     * Binds material uniforms and textures to the geometry shader
     */
    private function bindMaterial(Material $material): void
    {
        $this->geometryShader->setUniform4f(
            'u_albedo_color',
            $material->albedoColor->x,
            $material->albedoColor->y,
            $material->albedoColor->z,
            $material->albedoColor->w
        );
        $this->geometryShader->setUniform1f('u_metallic', $material->metallic);
        $this->geometryShader->setUniform1f('u_roughness', $material->roughness);
        $this->geometryShader->setUniformVec3('u_emissive_color', $material->emissiveColor);

        $flags = $material->getTextureFlags();
        $this->geometryShader->setUniform1i('u_texture_flags', $flags);

        $texUnit = 0;

        if ($material->albedoTexture !== null) {
            $material->albedoTexture->bind(GL_TEXTURE0 + $texUnit);
            $this->geometryShader->setUniform1i('u_albedo_map', $texUnit);
            $texUnit++;
        }
        if ($material->normalTexture !== null) {
            $material->normalTexture->bind(GL_TEXTURE0 + $texUnit);
            $this->geometryShader->setUniform1i('u_normal_map', $texUnit);
            $texUnit++;
        }
        if ($material->metallicRoughnessTexture !== null) {
            $material->metallicRoughnessTexture->bind(GL_TEXTURE0 + $texUnit);
            $this->geometryShader->setUniform1i('u_metallic_roughness_map', $texUnit);
            $texUnit++;
        }
        if ($material->aoTexture !== null) {
            $material->aoTexture->bind(GL_TEXTURE0 + $texUnit);
            $this->geometryShader->setUniform1i('u_ao_map', $texUnit);
            $texUnit++;
        }
        if ($material->emissiveTexture !== null) {
            $material->emissiveTexture->bind(GL_TEXTURE0 + $texUnit);
            $this->geometryShader->setUniform1i('u_emissive_map', $texUnit);
        }

        // double-sided
        if ($material->doubleSided) {
            glDisable(GL_CULL_FACE);
        } else {
            glEnable(GL_CULL_FACE);
        }
    }
}
