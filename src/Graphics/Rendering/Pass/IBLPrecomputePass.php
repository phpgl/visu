<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\Graphics\Cubemap;
use VISU\Graphics\Framebuffer;
use VISU\Graphics\GLState;
use VISU\Graphics\QuadVertexArray;
use VISU\Graphics\CubeVertexArray;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\CubemapResource;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\Texture;
use VISU\Graphics\TextureOptions;

class IBLPrecomputePass extends RenderPass
{
    public function __construct(
        private CubemapResource $environmentCubemap,
        private ShaderProgram $irradianceShader,
        private ShaderProgram $prefilterShader,
        private ShaderProgram $brdfShader,
        private ?IBLCache $cache = null,
        private int $irradianceSize = 32,
        private int $prefilterSize = 128,
        private int $brdfLutSize = 512,
    ) {
    }

    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $pipeline->reads($this, $this->environmentCubemap);

        $iblData = $data->create(IBLPrecomputeData::class);
        
        // create the irradiance resources
        $iblData->irradianceRenderTarget = $pipeline->createRenderTarget('ibl_precompute_irradiance_rt', $this->irradianceSize, $this->irradianceSize);
        $iblData->irradianceRenderTarget->createRenderbufferDepthStencil = true;
        $irradianceTextureOptions = new TextureOptions;
        $irradianceTextureOptions->internalFormat = GL_RGB16F;
        $irradianceTextureOptions->dataFormat = GL_RGB;
        $irradianceTextureOptions->dataType = GL_FLOAT;
        $irradianceTextureOptions->generateMipmaps = false;
        $irradianceTextureOptions->minFilter = GL_LINEAR;
        $irradianceTextureOptions->magFilter = GL_LINEAR;
        $irradianceTextureOptions->wrapS = GL_CLAMP_TO_EDGE;
        $irradianceTextureOptions->wrapT = GL_CLAMP_TO_EDGE;
        $iblData->irradianceCubemap = $pipeline->createCubemapColorAttachment($iblData->irradianceRenderTarget, 'ibl_precompute_irradiance', $irradianceTextureOptions);

        // create the prefilter resources
        $iblData->prefilterRenderTarget = $pipeline->createRenderTarget('ibl_precompute_prefilter_rt', $this->prefilterSize, $this->prefilterSize);
        $iblData->prefilterRenderTarget->createRenderbufferDepthStencil = true;
        $prefilterTextureOptions = new TextureOptions;
        $prefilterTextureOptions->internalFormat = GL_RGB16F;
        $prefilterTextureOptions->dataFormat = GL_RGB;
        $prefilterTextureOptions->dataType = GL_FLOAT;
        $prefilterTextureOptions->generateMipmaps = true;
        $prefilterTextureOptions->minFilter = GL_LINEAR_MIPMAP_LINEAR;
        $prefilterTextureOptions->magFilter = GL_LINEAR;
        $prefilterTextureOptions->wrapS = GL_CLAMP_TO_EDGE;
        $prefilterTextureOptions->wrapT = GL_CLAMP_TO_EDGE;
        $iblData->prefilterCubemap = $pipeline->createCubemapColorAttachment($iblData->prefilterRenderTarget, 'ibl_precompute_prefilter', $prefilterTextureOptions);

        // and the BRDF LUT resources
        $iblData->brdfLutRenderTarget = $pipeline->createRenderTarget('ibl_precompute_brdf_lut_rt', $this->brdfLutSize, $this->brdfLutSize);
        $iblData->brdfLutRenderTarget->createRenderbufferDepthStencil = false;
        $brdfLutTextureOptions = new TextureOptions;
        $brdfLutTextureOptions->internalFormat = GL_RG16F;
        $brdfLutTextureOptions->dataFormat = GL_RG;
        $brdfLutTextureOptions->dataType = GL_FLOAT;
        $brdfLutTextureOptions->generateMipmaps = false;
        $brdfLutTextureOptions->minFilter = GL_LINEAR;
        $brdfLutTextureOptions->magFilter = GL_LINEAR;
        $brdfLutTextureOptions->wrapS = GL_CLAMP_TO_EDGE;
        $brdfLutTextureOptions->wrapT = GL_CLAMP_TO_EDGE;
        $iblData->brdfLut = $pipeline->createColorAttachment($iblData->brdfLutRenderTarget, 'ibl_precompute_brdf_lut', $brdfLutTextureOptions);
        
        // calculate mip levels based on prefilter size (stop at 8x8 minimum)
        $minMipSize = 8;
        $iblData->prefilterMipLevels = (int) floor(log($this->prefilterSize / $minMipSize, 2)) + 1;
        
        // store the cache reference in the data for the light pass to use
        $iblData->cache = $this->cache;
    }

    /**
     * Forces the IBL maps to be recomputed on the next frame
     */
    public function invalidateCache(): void
    {
        if ($this->cache !== null) {
            $this->cache->invalidate();
        }
    }

    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        $iblData = $data->get(IBLPrecomputeData::class);
        $envCubemap = $resources->getCubemap($this->environmentCubemap);
        
        // check if the cache is valid for this environment cubemap
        if ($this->cache !== null && $this->cache->isValidFor($envCubemap)) {
            // cache is valid, skip the expensive computation
            return;
        }
        
        // cube geometry
        /** @var CubeVertexArray */
        $cubeVA = $resources->cacheStaticResource('ibl_cube_va', function(GLState $gl) {
            return new CubeVertexArray($gl);
        });

        /** @var QuadVertexArray */
        $quadVA = $resources->cacheStaticResource('ibl_quad_va', function(GLState $gl) {
            return new QuadVertexArray($gl);
        });


        // disable depth testing and culling for IBL baking
        glEnable(GL_DEPTH_TEST);
        glDisable(GL_CULL_FACE);

        // first the irradiance map
        $rt = $resources->activateRenderTarget($iblData->irradianceRenderTarget);
        $irradianceCubemap = $resources->getCubemap($iblData->irradianceCubemap);

        $captureViews = Cubemap::captureViews();
        $captureProjection = Cubemap::captureProjectionMatrix();

        $this->irradianceShader->use();
        $this->irradianceShader->setUniformMat4('projection', false, $captureProjection);
        $this->irradianceShader->bindTextures([
            'u_env_cubemap' => $envCubemap,
        ]);

        $cubeVA->bind();
        for ($face = 0; $face < 6; $face++) {
            $rt->offscreenFramebuffer()->attachTextureId(GL_COLOR_ATTACHMENT0, Cubemap::FACE_TARGETS[$face], $irradianceCubemap->id);   
            $rt->offscreenFramebuffer()->clear();

            $this->irradianceShader->setUniformMat4('view', false, $captureViews[$face]);

            $cubeVA->draw();
        }

        // next the prefiltered environment map
        $rt = $resources->activateRenderTarget($iblData->prefilterRenderTarget);
        $prefilterCubemap = $resources->getCubemap($iblData->prefilterCubemap);
        
        $this->prefilterShader->use();
        $this->prefilterShader->setUniformMat4('projection', false, $captureProjection);
        $this->prefilterShader->setUniform1f('u_source_resolution', (float) $envCubemap->size());
        $this->prefilterShader->bindTextures([
            'u_env_cubemap' => $envCubemap,
        ]);
        
        // use the mip level count calculated in setup()
        $maxMipLevels = $iblData->prefilterMipLevels;
        for ($mip = 0; $mip < $maxMipLevels; $mip++) {
            // resize framebuffer according to mip-level size
            $mipWidth = (int) ($this->prefilterSize * pow(0.5, $mip));
            $mipHeight = (int) ($this->prefilterSize * pow(0.5, $mip));
            $rt->resize($mipWidth, $mipHeight);

            $roughness = $mip / ($maxMipLevels - 1);
            $this->prefilterShader->setUniform1f('u_roughness', $roughness);

            for ($face = 0; $face < 6; $face++) {
                $rt->offscreenFramebuffer()->attachTextureId(GL_COLOR_ATTACHMENT0, Cubemap::FACE_TARGETS[$face], $prefilterCubemap->id, $mip);
                $rt->offscreenFramebuffer()->clear();

                $this->prefilterShader->setUniformMat4('view', false, $captureViews[$face]);

                $cubeVA->draw();
            }
        }

        // now that all mip levels are rendered, enable mipmap filtering
        $prefilterCubemap->bind();
        glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_MIN_FILTER, GL_LINEAR_MIPMAP_LINEAR);

        // finally the BRDF LUT
        $rt = $resources->activateRenderTarget($iblData->brdfLutRenderTarget);
        $rt->offscreenFramebuffer()->clear();
        $this->brdfShader->use();

        $quadVA->bind();
        $quadVA->draw();

        // mark the cache as valid for this environment cubemap
        if ($this->cache !== null) {
            $this->cache->markValid($envCubemap);
        }
    }
}
