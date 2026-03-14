<?php

namespace VISU\Graphics\Rendering\Pass;

use GL\Math\Mat4;
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

class MotionBlurPass extends RenderPass
{
    private RenderTargetResource $outputTarget;
    private TextureResource $outputTexture;

    /**
     * @param ?Mat4 $previousViewProjection VP matrix from previous frame (null on first frame)
     */
    public function __construct(
        private ShaderProgram $motionBlurShader,
        private TextureResource $inputTexture,
        private TextureResource $depthTexture,
        private ?Mat4 $previousViewProjection,
        private float $blurStrength = 1.0,
        private int $numSamples = 8,
    ) {
    }

    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $pipeline->reads($this, $this->inputTexture);
        $pipeline->reads($this, $this->depthTexture);

        $gbufferData = $data->get(GBufferPassData::class);

        $hdrOptions = new TextureOptions();
        $hdrOptions->internalFormat = GL_RGBA16F;
        $hdrOptions->dataFormat = GL_RGBA;
        $hdrOptions->dataType = GL_FLOAT;
        $hdrOptions->minFilter = GL_LINEAR;
        $hdrOptions->magFilter = GL_LINEAR;
        $hdrOptions->wrapS = GL_CLAMP_TO_EDGE;
        $hdrOptions->wrapT = GL_CLAMP_TO_EDGE;

        $this->outputTarget = $pipeline->createRenderTarget(
            'motion_blur',
            $gbufferData->renderTarget->width,
            $gbufferData->renderTarget->height
        );
        $this->outputTexture = $pipeline->createColorAttachment($this->outputTarget, 'motion_blur_output', $hdrOptions);

        $postData = $data->create(PostProcessData::class);
        $postData->renderTarget = $this->outputTarget;
        $postData->output = $this->outputTexture;
    }

    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        $cameraData = $data->get(CameraData::class);

        /** @var Mat4 $currentVP */
        $currentVP = $cameraData->projection * $cameraData->view;

        // on first frame, use current VP as previous (no motion blur)
        $prevVP = $this->previousViewProjection ?? $currentVP;

        $currentVPInverse = $currentVP->copy();
        $currentVPInverse->inverse();

        /** @var QuadVertexArray */
        $quadVA = $resources->cacheStaticResource('quadva', function (GLState $gl) {
            return new QuadVertexArray($gl);
        });

        glDisable(GL_DEPTH_TEST);

        $resources->activateRenderTarget($this->outputTarget);
        $this->motionBlurShader->use();

        $resources->getTexture($this->inputTexture)->bind(GL_TEXTURE0);
        $this->motionBlurShader->setUniform1i('u_scene', 0);

        $resources->getTexture($this->depthTexture)->bind(GL_TEXTURE1);
        $this->motionBlurShader->setUniform1i('u_depth', 1);

        $this->motionBlurShader->setUniformMatrix4f('u_current_vp_inverse', false, $currentVPInverse);
        $this->motionBlurShader->setUniformMatrix4f('u_previous_vp', false, $prevVP);
        $this->motionBlurShader->setUniform1f('u_blur_strength', $this->blurStrength);
        $this->motionBlurShader->setUniform1i('u_num_samples', $this->numSamples);

        $quadVA->draw();
    }
}
