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

class DepthOfFieldPass extends RenderPass
{
    private RenderTargetResource $blurHTarget;
    private TextureResource $blurHTexture;

    private RenderTargetResource $blurVTarget;
    private TextureResource $blurVTexture;

    private RenderTargetResource $dofTarget;
    private TextureResource $dofTexture;

    public function __construct(
        private ShaderProgram $blurShader,
        private ShaderProgram $dofShader,
        private TextureResource $inputTexture,
        private TextureResource $depthTexture,
        private float $focusDistance = 10.0,
        private float $focusRange = 5.0,
        private float $nearPlane = 0.1,
        private float $farPlane = 1000.0,
        private float $maxBlur = 1.0,
        private float $downscale = 0.5,
    ) {
    }

    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $pipeline->reads($this, $this->inputTexture);
        $pipeline->reads($this, $this->depthTexture);

        $gbufferData = $data->get(GBufferPassData::class);
        $w = (int)($gbufferData->renderTarget->width * $this->downscale);
        $h = (int)($gbufferData->renderTarget->height * $this->downscale);
        $fullW = $gbufferData->renderTarget->width;
        $fullH = $gbufferData->renderTarget->height;

        $hdrOptions = new TextureOptions();
        $hdrOptions->internalFormat = GL_RGBA16F;
        $hdrOptions->dataFormat = GL_RGBA;
        $hdrOptions->dataType = GL_FLOAT;
        $hdrOptions->minFilter = GL_LINEAR;
        $hdrOptions->magFilter = GL_LINEAR;
        $hdrOptions->wrapS = GL_CLAMP_TO_EDGE;
        $hdrOptions->wrapT = GL_CLAMP_TO_EDGE;

        // blur passes at reduced resolution
        $this->blurHTarget = $pipeline->createRenderTarget('dof_blur_h', $w, $h);
        $this->blurHTexture = $pipeline->createColorAttachment($this->blurHTarget, 'dof_blur_h', $hdrOptions);

        $this->blurVTarget = $pipeline->createRenderTarget('dof_blur_v', $w, $h);
        $this->blurVTexture = $pipeline->createColorAttachment($this->blurVTarget, 'dof_blur_v', $hdrOptions);

        // final DoF composite at full resolution
        $this->dofTarget = $pipeline->createRenderTarget('dof_composite', $fullW, $fullH);
        $this->dofTexture = $pipeline->createColorAttachment($this->dofTarget, 'dof_output', $hdrOptions);

        $postData = $data->create(PostProcessData::class);
        $postData->renderTarget = $this->dofTarget;
        $postData->output = $this->dofTexture;
    }

    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        /** @var QuadVertexArray */
        $quadVA = $resources->cacheStaticResource('quadva', function (GLState $gl) {
            return new QuadVertexArray($gl);
        });

        glDisable(GL_DEPTH_TEST);

        $w = $this->blurHTarget->width;
        $h = $this->blurHTarget->height;

        // horizontal blur at reduced resolution
        $resources->activateRenderTarget($this->blurHTarget);
        $this->blurShader->use();
        $this->blurShader->setUniform2f('u_direction', 1.0 / $w, 0.0);
        $this->blurShader->setUniform1i('u_texture', 0);
        $resources->getTexture($this->inputTexture)->bind(GL_TEXTURE0);
        $quadVA->draw();

        // vertical blur
        $resources->activateRenderTarget($this->blurVTarget);
        $this->blurShader->use();
        $this->blurShader->setUniform2f('u_direction', 0.0, 1.0 / $h);
        $this->blurShader->setUniform1i('u_texture', 0);
        $resources->getTexture($this->blurHTexture)->bind(GL_TEXTURE0);
        $quadVA->draw();

        // DoF composite: mix sharp and blurred based on depth
        $resources->activateRenderTarget($this->dofTarget);
        $this->dofShader->use();

        $resources->getTexture($this->inputTexture)->bind(GL_TEXTURE0);
        $this->dofShader->setUniform1i('u_scene', 0);

        $resources->getTexture($this->depthTexture)->bind(GL_TEXTURE1);
        $this->dofShader->setUniform1i('u_depth', 1);

        $resources->getTexture($this->blurVTexture)->bind(GL_TEXTURE2);
        $this->dofShader->setUniform1i('u_blurred', 2);

        $this->dofShader->setUniform1f('u_focus_distance', $this->focusDistance);
        $this->dofShader->setUniform1f('u_focus_range', $this->focusRange);
        $this->dofShader->setUniform1f('u_near_plane', $this->nearPlane);
        $this->dofShader->setUniform1f('u_far_plane', $this->farPlane);
        $this->dofShader->setUniform1f('u_max_blur', $this->maxBlur);

        $quadVA->draw();
    }
}
