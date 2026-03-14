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
use VISU\Graphics\TextureOptions;

class BloomPass extends RenderPass
{
    private RenderTargetResource $extractTarget;
    private TextureResource $extractTexture;

    private RenderTargetResource $blurHTarget;
    private TextureResource $blurHTexture;

    private RenderTargetResource $blurVTarget;
    private TextureResource $blurVTexture;

    private RenderTargetResource $compositeTarget;
    private TextureResource $compositeTexture;

    public function __construct(
        private ShaderProgram $extractShader,
        private ShaderProgram $blurShader,
        private ShaderProgram $compositeShader,
        private TextureResource $inputTexture,
        private float $threshold = 1.0,
        private float $softThreshold = 0.5,
        private float $intensity = 1.0,
        private int $blurPasses = 2,
        private float $downscale = 0.5,
    ) {
    }

    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $pipeline->reads($this, $this->inputTexture);

        $postData = $data->create(PostProcessData::class);

        $gbufferData = $data->get(GBufferPassData::class);
        $w = (int)($gbufferData->renderTarget->width * $this->downscale);
        $h = (int)($gbufferData->renderTarget->height * $this->downscale);

        $hdrOptions = new TextureOptions();
        $hdrOptions->internalFormat = GL_RGBA16F;
        $hdrOptions->dataFormat = GL_RGBA;
        $hdrOptions->dataType = GL_FLOAT;
        $hdrOptions->minFilter = GL_LINEAR;
        $hdrOptions->magFilter = GL_LINEAR;
        $hdrOptions->wrapS = GL_CLAMP_TO_EDGE;
        $hdrOptions->wrapT = GL_CLAMP_TO_EDGE;

        // bright extract
        $this->extractTarget = $pipeline->createRenderTarget('bloom_extract', $w, $h);
        $this->extractTexture = $pipeline->createColorAttachment($this->extractTarget, 'bloom_bright', $hdrOptions);

        // blur H
        $this->blurHTarget = $pipeline->createRenderTarget('bloom_blur_h', $w, $h);
        $this->blurHTexture = $pipeline->createColorAttachment($this->blurHTarget, 'bloom_blur_h', $hdrOptions);

        // blur V
        $this->blurVTarget = $pipeline->createRenderTarget('bloom_blur_v', $w, $h);
        $this->blurVTexture = $pipeline->createColorAttachment($this->blurVTarget, 'bloom_blur_v', $hdrOptions);

        // composite output (full resolution)
        $fullW = $gbufferData->renderTarget->width;
        $fullH = $gbufferData->renderTarget->height;
        $this->compositeTarget = $pipeline->createRenderTarget('bloom_composite', $fullW, $fullH);
        $this->compositeTexture = $pipeline->createColorAttachment($this->compositeTarget, 'bloom_output', $hdrOptions);

        $postData->renderTarget = $this->compositeTarget;
        $postData->output = $this->compositeTexture;
    }

    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        /** @var QuadVertexArray */
        $quadVA = $resources->cacheStaticResource('quadva', function (GLState $gl) {
            return new QuadVertexArray($gl);
        });

        glDisable(GL_DEPTH_TEST);

        // 1. Extract bright pixels
        $resources->activateRenderTarget($this->extractTarget);
        $this->extractShader->use();
        $this->extractShader->setUniform1f('u_threshold', $this->threshold);
        $this->extractShader->setUniform1f('u_soft_threshold', $this->softThreshold);

        $inputTex = $resources->getTexture($this->inputTexture);
        $inputTex->bind(GL_TEXTURE0);
        $this->extractShader->setUniform1i('u_texture', 0);
        $quadVA->draw();

        // 2. Ping-pong Gaussian blur
        $w = $this->extractTarget->width;
        $h = $this->extractTarget->height;

        for ($i = 0; $i < $this->blurPasses; $i++) {
            // horizontal blur
            $resources->activateRenderTarget($this->blurHTarget);
            $this->blurShader->use();
            $this->blurShader->setUniform2f('u_direction', 1.0 / $w, 0.0);
            $this->blurShader->setUniform1i('u_texture', 0);

            if ($i === 0) {
                $resources->getTexture($this->extractTexture)->bind(GL_TEXTURE0);
            } else {
                $resources->getTexture($this->blurVTexture)->bind(GL_TEXTURE0);
            }
            $quadVA->draw();

            // vertical blur
            $resources->activateRenderTarget($this->blurVTarget);
            $this->blurShader->use();
            $this->blurShader->setUniform2f('u_direction', 0.0, 1.0 / $h);
            $this->blurShader->setUniform1i('u_texture', 0);
            $resources->getTexture($this->blurHTexture)->bind(GL_TEXTURE0);
            $quadVA->draw();
        }

        // 3. Composite bloom with original scene
        $resources->activateRenderTarget($this->compositeTarget);
        $this->compositeShader->use();

        $inputTex->bind(GL_TEXTURE0);
        $this->compositeShader->setUniform1i('u_scene', 0);

        $resources->getTexture($this->blurVTexture)->bind(GL_TEXTURE1);
        $this->compositeShader->setUniform1i('u_bloom', 1);

        $this->compositeShader->setUniform1f('u_bloom_intensity', $this->intensity);
        $quadVA->draw();
    }
}
