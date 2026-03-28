<?php

namespace VISU\Graphics\Rendering\Pass;

use GL\Math\{GLM, Quat, Vec3};
use VISU\Component\DirectionalLightComponent;
use VISU\Component\PointLightComponent;
use VISU\Component\SpotLightComponent;
use VISU\ECS\EntitiesInterface;
use VISU\Geo\Transform;
use VISU\Graphics\GLState;
use VISU\Graphics\QuadVertexArray;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\ShaderProgram;

class PBRDeferredLightPass extends RenderPass
{
    const MAX_POINT_LIGHTS = 32;
    const MAX_SPOT_LIGHTS = 16;

    public function __construct(
        private ShaderProgram $lightingShader,
        private DirectionalLightComponent $sun,
        private EntitiesInterface $entities,
    ) {
    }

    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $gbufferData = $data->get(GBufferPassData::class);
        $pbrGbufferData = $data->get(PBRGBufferData::class);
        $lightpassData = $data->create(DeferredLightPassData::class);

        $pipeline->reads($this, $gbufferData->albedoTexture);
        $pipeline->reads($this, $gbufferData->normalTexture);
        $pipeline->reads($this, $gbufferData->worldSpacePositionTexture);
        $pipeline->reads($this, $pbrGbufferData->metallicRoughnessTexture);
        $pipeline->reads($this, $pbrGbufferData->emissiveTexture);

        $lightpassData->renderTarget = $pipeline->createRenderTarget(
            'lightpass',
            $gbufferData->renderTarget->width,
            $gbufferData->renderTarget->height
        );
        $lightpassData->output = $pipeline->createColorAttachment(
            $lightpassData->renderTarget, 'lightpass_output'
        );
    }

    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        $gbufferData = $data->get(GBufferPassData::class);
        $pbrGbufferData = $data->get(PBRGBufferData::class);
        $cameraData = $data->get(CameraData::class);
        $lightpassData = $data->get(DeferredLightPassData::class);
        $ssaoData = $data->get(SSAOData::class);

        $resources->activateRenderTarget($lightpassData->renderTarget);

        /** @var QuadVertexArray */
        $quadVA = $resources->cacheStaticResource('quadva', function(GLState $gl) {
            return new QuadVertexArray($gl);
        });

        $this->lightingShader->use();
        $this->lightingShader->setUniformVec3('camera_position', $cameraData->renderCamera->transform->position);
        $this->lightingShader->setUniform2f('camera_resolution', $cameraData->resolutionX, $cameraData->resolutionY);

        // directional light (sun)
        $this->lightingShader->setUniformVec3('sun_direction', $this->sun->direction);
        $this->lightingShader->setUniformVec3('sun_color', $this->sun->color);
        $this->lightingShader->setUniform1f('sun_intensity', $this->sun->intensity);

        // view matrix for cascade depth calculation
        $this->lightingShader->setUniformMatrix4f('u_view_matrix', false, $cameraData->view);

        // point lights — also track which are shadow-casting for cubemap binding
        $lightIndex = 0;
        /** @var array<int, int> Maps shadow light index => point_lights[] array index */
        $shadowLightMapping = [];
        foreach ($this->entities->view(PointLightComponent::class) as $entity => $light) {
            if ($lightIndex >= self::MAX_POINT_LIGHTS) break;

            $transform = $this->entities->get($entity, Transform::class);
            $worldPos = $transform->getWorldPosition($this->entities);
            $prefix = "point_lights[{$lightIndex}]";

            $this->lightingShader->setUniformVec3("{$prefix}.position", $worldPos);
            $this->lightingShader->setUniformVec3("{$prefix}.color", $light->color);
            $this->lightingShader->setUniform1f("{$prefix}.intensity", $light->intensity);
            $this->lightingShader->setUniform1f("{$prefix}.range", $light->range);
            $this->lightingShader->setUniform1f("{$prefix}.constant", $light->constantAttenuation);
            $this->lightingShader->setUniform1f("{$prefix}.linear", $light->linearAttenuation);
            $this->lightingShader->setUniform1f("{$prefix}.quadratic", $light->quadraticAttenuation);

            if ($light->castsShadows && count($shadowLightMapping) < PointLightShadowPass::MAX_SHADOW_POINT_LIGHTS) {
                $shadowLightMapping[count($shadowLightMapping)] = $lightIndex;
            }

            $lightIndex++;
        }
        $this->lightingShader->setUniform1i('num_point_lights', $lightIndex);

        // spot lights
        $spotIndex = 0;
        foreach ($this->entities->view(SpotLightComponent::class) as $entity => $spot) {
            if ($spotIndex >= self::MAX_SPOT_LIGHTS) break;

            $transform = $this->entities->get($entity, Transform::class);
            $worldPos = $transform->getWorldPosition($this->entities);

            // transform local direction by entity orientation
            $worldDir = Quat::multiplyVec3(
                $transform->getWorldOrientation($this->entities),
                $spot->direction,
            );

            $prefix = "spot_lights[{$spotIndex}]";

            $this->lightingShader->setUniformVec3("{$prefix}.position", $worldPos);
            $this->lightingShader->setUniformVec3("{$prefix}.direction", $worldDir);
            $this->lightingShader->setUniformVec3("{$prefix}.color", $spot->color);
            $this->lightingShader->setUniform1f("{$prefix}.intensity", $spot->intensity);
            $this->lightingShader->setUniform1f("{$prefix}.range", $spot->range);
            $this->lightingShader->setUniform1f("{$prefix}.constant", $spot->constantAttenuation);
            $this->lightingShader->setUniform1f("{$prefix}.linear", $spot->linearAttenuation);
            $this->lightingShader->setUniform1f("{$prefix}.quadratic", $spot->quadraticAttenuation);
            $this->lightingShader->setUniform1f("{$prefix}.innerCutoff", cos(GLM::radians($spot->innerAngle)));
            $this->lightingShader->setUniform1f("{$prefix}.outerCutoff", cos(GLM::radians($spot->outerAngle)));
            $spotIndex++;
        }
        $this->lightingShader->setUniform1i('num_spot_lights', $spotIndex);

        // bind GBuffer textures
        $texUnit = 0;
        $textureBindings = [
            [$gbufferData->worldSpacePositionTexture, 'gbuffer_position'],
            [$gbufferData->normalTexture, 'gbuffer_normal'],
            [$gbufferData->depthTexture, 'gbuffer_depth'],
            [$gbufferData->albedoTexture, 'gbuffer_albedo'],
            [$ssaoData->blurTexture, 'gbuffer_ao'],
            [$pbrGbufferData->metallicRoughnessTexture, 'gbuffer_metallic_roughness'],
            [$pbrGbufferData->emissiveTexture, 'gbuffer_emissive'],
        ];

        foreach ($textureBindings as [$texture, $name]) {
            $glTexture = $resources->getTexture($texture);
            $glTexture->bind(GL_TEXTURE0 + $texUnit);
            $this->lightingShader->setUniform1i($name, $texUnit);
            $texUnit++;
        }

        // bind shadow maps and set cascade uniforms
        $hasShadows = $data->has(ShadowMapData::class);
        if ($hasShadows) {
            $shadowData = $data->get(ShadowMapData::class);
        }
        if ($hasShadows && $shadowData->cascadeCount > 0) {
            $this->lightingShader->setUniform1i('num_shadow_cascades', $shadowData->cascadeCount);

            for ($i = 0; $i < $shadowData->cascadeCount; $i++) {
                $shadowTex = $resources->getTexture($shadowData->depthTextures[$i]);
                $shadowTex->bind(GL_TEXTURE0 + $texUnit);
                $this->lightingShader->setUniform1i("shadow_map_{$i}", $texUnit);
                $texUnit++;

                $this->lightingShader->setUniformMatrix4f("light_space_matrices[{$i}]", false, $shadowData->lightSpaceMatrices[$i]);
                $this->lightingShader->setUniform1f("cascade_splits[{$i}]", $shadowData->cascadeSplits[$i]);
            }
        } else {
            $this->lightingShader->setUniform1i('num_shadow_cascades', 0);
        }

        // ensure all shadow map samplers are bound to valid textures (macOS requires this)
        /** @var \VISU\Graphics\Texture $dummyTex2D */
        $dummyTex2D = $resources->cacheStaticResource('pbr_dummy_shadow_tex', function(GLState $gl) {
            $tex = new \VISU\Graphics\Texture($gl, 'dummy_shadow');
            $opts = new \VISU\Graphics\TextureOptions;
            $opts->internalFormat = GL_DEPTH_COMPONENT;
            $opts->dataFormat = GL_DEPTH_COMPONENT;
            $opts->dataType = GL_FLOAT;
            $opts->minFilter = GL_NEAREST;
            $opts->magFilter = GL_NEAREST;
            $opts->generateMipmaps = false;
            $tex->allocateEmpty(1, 1, $opts);
            return $tex;
        });
        $maxCascades = ($hasShadows && $shadowData->cascadeCount > 0) ? $shadowData->cascadeCount : 0;
        for ($i = $maxCascades; $i < 4; $i++) {
            $dummyTex2D->bind(GL_TEXTURE0 + $texUnit);
            $this->lightingShader->setUniform1i("shadow_map_{$i}", $texUnit);
            $texUnit++;
        }

        // bind point light cubemap shadows
        $hasPointShadows = $data->has(PointLightShadowData::class);
        if ($hasPointShadows) {
            $pointShadowData = $data->get(PointLightShadowData::class);
        }
        if ($hasPointShadows && $pointShadowData->shadowLightCount > 0) {
            $this->lightingShader->setUniform1i('num_point_shadow_lights', $pointShadowData->shadowLightCount);

            for ($i = 0; $i < $pointShadowData->shadowLightCount; $i++) {
                // bind cubemap texture
                glActiveTexture(GL_TEXTURE0 + $texUnit);
                glBindTexture(GL_TEXTURE_CUBE_MAP, $pointShadowData->cubemapTextureIds[$i]);
                $this->lightingShader->setUniform1i("point_shadow_map_{$i}", $texUnit);
                $texUnit++;

                $this->lightingShader->setUniformVec3("point_shadow_positions[{$i}]", $pointShadowData->lightPositions[$i]);
                $this->lightingShader->setUniform1f("point_shadow_far_planes[{$i}]", $pointShadowData->farPlanes[$i]);
                $this->lightingShader->setUniform1i("point_shadow_light_indices[{$i}]", $shadowLightMapping[$i] ?? -1);
            }
        } else {
            $this->lightingShader->setUniform1i('num_point_shadow_lights', 0);
        }

        // ensure all point shadow cubemap samplers are bound (macOS requires complete textures)
        /** @var int $dummyCubemapId */
        $dummyCubemapId = $resources->cacheStaticResource('pbr_dummy_cubemap_id', function(GLState $gl) {
            $id = 0;
            glGenTextures(1, $id);
            glBindTexture(GL_TEXTURE_CUBE_MAP, $id);
            for ($face = 0; $face < 6; $face++) {
                glTexImage2D(
                    GL_TEXTURE_CUBE_MAP_POSITIVE_X + $face,
                    0, GL_DEPTH_COMPONENT, 1, 1, 0,
                    GL_DEPTH_COMPONENT, GL_FLOAT, null
                );
            }
            glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_MIN_FILTER, GL_NEAREST);
            glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_MAG_FILTER, GL_NEAREST);
            glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_WRAP_S, GL_CLAMP_TO_EDGE);
            glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_WRAP_T, GL_CLAMP_TO_EDGE);
            glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_WRAP_R, GL_CLAMP_TO_EDGE);
            return $id;
        });
        $maxPointShadows = ($hasPointShadows && $pointShadowData->shadowLightCount > 0) ? $pointShadowData->shadowLightCount : 0;
        for ($i = $maxPointShadows; $i < 4; $i++) {
            glActiveTexture(GL_TEXTURE0 + $texUnit);
            glBindTexture(GL_TEXTURE_CUBE_MAP, $dummyCubemapId);
            $this->lightingShader->setUniform1i("point_shadow_map_{$i}", $texUnit);
            $texUnit++;
        }

        glDisable(GL_DEPTH_TEST);
        glEnable(GL_CULL_FACE);

        $quadVA->bind();
        $quadVA->draw();
    }
}
