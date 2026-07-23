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
use VISU\Graphics\Rendering\Resource\CubemapResource;
use VISU\Graphics\Rendering\Resource\TextureResource;
use VISU\Graphics\ShaderCollection;
use VISU\Graphics\ShaderProgram;

class DeferredLightPass extends RenderPass
{
    /**
     * The name of the shader in the collection
     */
    public const SHADER_NAME = 'visu/lowpoly/deferred_lightpass';

    /**
     * Constructor
     */
    public function __construct(
        private ShaderCollection $shaders,
        private DirectionalLightComponent $sun,
        private ?CubemapResource $environmentCubemap = null,
    )
    {
        // register lighting shader permutations for different environment setups
        $this->shaders->definePermutations(
            self::SHADER_NAME,
            DeferredLightPassPermutation::getAllPermutationDefines()
        );
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
        $pipeline->reads($this, $gbufferData->metallicTexture);
        $pipeline->reads($this, $gbufferData->roughnessTexture);
        $pipeline->reads($this, $gbufferData->emissiveTexture);

        // read environment cubemap if available
        if ($this->environmentCubemap) {
            $pipeline->reads($this, $this->environmentCubemap);
        }

        // read IBL precompute data if available
        if ($data->has(IBLPrecomputeData::class)) {
            $iblData = $data->get(IBLPrecomputeData::class);
            $pipeline->reads($this, $iblData->irradianceCubemap);
            $pipeline->reads($this, $iblData->prefilterCubemap);
            $pipeline->reads($this, $iblData->brdfLut);
        }

        // create light pass target with the same size as the gbuffer
        $lightpassData->renderTarget = $pipeline->createRenderTarget('lightpass', $gbufferData->renderTarget->width, $gbufferData->renderTarget->height);
        $lightpassData->output = $pipeline->createColorAttachment($lightpassData->renderTarget, 'lightpass_output');
    }

    /**
     * Determines which shader permutation to use based on available resources
     */
    private function determinePermutation(PipelineContainer $data, PipelineResources $resources): DeferredLightPassPermutation
    {
        // check if IBL data is available and valid
        if ($data->has(IBLPrecomputeData::class)) {
            $iblData = $data->get(IBLPrecomputeData::class);

            $irradianceCubemap = $resources->getCubemap($iblData->irradianceCubemap);
            $prefilterCubemap = $resources->getCubemap($iblData->prefilterCubemap);
            $brdfLut = $resources->getTexture($iblData->brdfLut);

            // full IBL when all three IBL textures are available
            if ($irradianceCubemap && $irradianceCubemap->id > 0 &&
                $prefilterCubemap && $prefilterCubemap->id > 0 &&
                $brdfLut && $brdfLut->id > 0) {
                return DeferredLightPassPermutation::IBL;
            }
        }

        // check if environment cubemap is available
        if ($this->environmentCubemap) {
            $glCubemap = $resources->getCubemap($this->environmentCubemap);
            if ($glCubemap && $glCubemap->id > 0) {
                return DeferredLightPassPermutation::envCubemap;
            }
        }

        // basic lighting only
        return DeferredLightPassPermutation::basic;
    }

    /**
     * Executes the render pass
     */
    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        $gbufferData = $data->get(GBufferPassData::class);
        $cameraData = $data->get(CameraData::class);
        $lightpassData = $data->get(DeferredLightPassData::class);
        $ssaoData = $data->get(SSAOData::class);

        $resources->activateRenderTarget($lightpassData->renderTarget);

        /** @var QuadVertexArray */
        $quadVA = $resources->cacheStaticResource('quadva', function(GLState $gl) {
            return new QuadVertexArray($gl);
        });

        // determine the appropriate shader permutation
        $permutation = $this->determinePermutation($data, $resources);
        $shader = $this->shaders->getPermutation(self::SHADER_NAME, $permutation->value);

        // prepare the shader
        $shader->use();
        $shader->setUniformVec3('camera_position', $cameraData->renderCamera->transform->position);
        $shader->setUniform2f('camera_resolution', $cameraData->resolutionX, $cameraData->resolutionY);

        // set sun properties (use values from sun component directly)
        $sunDir = $this->sun->direction->copy();
        $sunDir->normalize();

        $shader->setUniformVec3('sun_direction', $sunDir);
        $shader->setUniformVec3('sun_color', $this->sun->color);
        $shader->setUniform1f('sun_intensity', $this->sun->intensity);

        // bind the gbuffer textures
        $textureUnit = 0;
        foreach([
            [$gbufferData->worldSpacePositionTexture, 'position'],
            [$gbufferData->normalTexture, 'normal'],
            [$gbufferData->depthTexture, 'depth'],
            [$gbufferData->albedoTexture, 'albedo'],
            [$gbufferData->metallicTexture, 'metallic'],
            [$gbufferData->roughnessTexture, 'roughness'],
            [$gbufferData->emissiveTexture, 'emissive'],
            [$ssaoData->blurTexture, 'ao'],
        ] as $i => $tuple) {
            list($texture, $name) = $tuple;
            $glTexture = $resources->getTexture($texture);
            $glTexture->bind(GL_TEXTURE0 + $textureUnit);
            $shader->setUniform1i('gbuffer_' . $name, $textureUnit);
            $textureUnit++;
        }

        // bind permutation-specific uniforms
        if ($permutation === DeferredLightPassPermutation::envCubemap) {
            $glCubemap = $resources->getCubemap($this->environmentCubemap);
            $glCubemap->bind(GL_TEXTURE0 + $textureUnit);
            $shader->setUniform1i('environment_cubemap', $textureUnit);

            // clamp mip count to a valid range so the shader can use lod based sampling
            $mipCount = max(1, (int) floor(log(max($glCubemap->size(), 1), 2)) + 1);
            $shader->setUniform1i('environment_mip_count', $mipCount);
            $textureUnit++;
        }
        elseif ($permutation === DeferredLightPassPermutation::IBL) {
            $iblData = $data->get(IBLPrecomputeData::class);

            // irradiance map
            $irradianceCubemap = $resources->getCubemap($iblData->irradianceCubemap);
            $irradianceCubemap->bind(GL_TEXTURE0 + $textureUnit);
            $shader->setUniform1i('ibl_irradiance_map', $textureUnit);
            $textureUnit++;

            // prefiltered environment map
            $prefilterCubemap = $resources->getCubemap($iblData->prefilterCubemap);
            $prefilterCubemap->bind(GL_TEXTURE0 + $textureUnit);
            $shader->setUniform1i('ibl_prefilter_map', $textureUnit);
            $shader->setUniform1i('prefilter_mip_count', $iblData->prefilterMipLevels);
            $textureUnit++;

            // brdf lookup texture
            $brdfLut = $resources->getTexture($iblData->brdfLut);
            $brdfLut->bind(GL_TEXTURE0 + $textureUnit);
            $shader->setUniform1i('ibl_brdf_lut', $textureUnit);
            $textureUnit++;
        }

        // enable seamless cubemap sampling for IBL
        glEnable(GL_TEXTURE_CUBE_MAP_SEAMLESS);

        glDisable(GL_DEPTH_TEST);
        glEnable(GL_CULL_FACE);

        $quadVA->bind();
        $quadVA->draw();
    }
}
