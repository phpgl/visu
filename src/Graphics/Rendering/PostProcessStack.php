<?php

namespace VISU\Graphics\Rendering;

use GL\Math\Mat4;
use VISU\Graphics\Rendering\Pass\BloomPass;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\Pass\DepthOfFieldPass;
use VISU\Graphics\Rendering\Pass\GBufferPassData;
use VISU\Graphics\Rendering\Pass\MotionBlurPass;
use VISU\Graphics\Rendering\Pass\PostProcessData;
use VISU\Graphics\Rendering\Resource\TextureResource;
use VISU\Graphics\ShaderCollection;
use VISU\Graphics\ShaderProgram;

class PostProcessStack
{
    // Bloom settings
    public bool $bloomEnabled = false;
    public float $bloomThreshold = 1.0;
    public float $bloomSoftThreshold = 0.5;
    public float $bloomIntensity = 1.0;
    public int $bloomBlurPasses = 2;
    public float $bloomDownscale = 0.5;

    // Depth of Field settings
    public bool $dofEnabled = false;
    public float $dofFocusDistance = 10.0;
    public float $dofFocusRange = 5.0;
    public float $dofMaxBlur = 1.0;
    public float $dofNearPlane = 0.1;
    public float $dofFarPlane = 1000.0;
    public float $dofDownscale = 0.5;

    // Motion Blur settings
    public bool $motionBlurEnabled = false;
    public float $motionBlurStrength = 1.0;
    public int $motionBlurSamples = 8;

    private ShaderProgram $bloomExtractShader;
    private ShaderProgram $blurShader;
    private ShaderProgram $bloomCompositeShader;
    private ShaderProgram $dofShader;
    private ShaderProgram $motionBlurShader;

    private ?Mat4 $previousViewProjection = null;

    public function __construct(
        private ShaderCollection $shaders,
    ) {
        $this->bloomExtractShader = $this->shaders->get('visu/postprocess/bloom_extract');
        $this->blurShader = $this->shaders->get('visu/postprocess/blur_gaussian');
        $this->bloomCompositeShader = $this->shaders->get('visu/postprocess/bloom_composite');
        $this->dofShader = $this->shaders->get('visu/postprocess/dof');
        $this->motionBlurShader = $this->shaders->get('visu/postprocess/motion_blur');
    }

    public function hasActiveEffects(): bool
    {
        return $this->bloomEnabled || $this->dofEnabled || $this->motionBlurEnabled;
    }

    /**
     * Attaches post-processing passes to the pipeline.
     * Returns the final output texture resource to copy to the backbuffer.
     */
    public function attachPasses(
        RenderPipeline $pipeline,
        PipelineContainer $data,
        TextureResource $sceneTexture,
    ): TextureResource {
        $currentInput = $sceneTexture;

        $gbufferData = $data->get(GBufferPassData::class);
        $depthTexture = $gbufferData->depthTexture;

        if ($this->bloomEnabled) {
            $pipeline->addPass(new BloomPass(
                $this->bloomExtractShader,
                $this->blurShader,
                $this->bloomCompositeShader,
                $currentInput,
                $this->bloomThreshold,
                $this->bloomSoftThreshold,
                $this->bloomIntensity,
                $this->bloomBlurPasses,
                $this->bloomDownscale,
            ));
            $postData = $data->get(PostProcessData::class);
            $currentInput = $postData->output;
        }

        if ($this->dofEnabled) {
            $pipeline->addPass(new DepthOfFieldPass(
                $this->blurShader,
                $this->dofShader,
                $currentInput,
                $depthTexture,
                $this->dofFocusDistance,
                $this->dofFocusRange,
                $this->dofNearPlane,
                $this->dofFarPlane,
                $this->dofMaxBlur,
                $this->dofDownscale,
            ));
            $postData = $data->get(PostProcessData::class);
            $currentInput = $postData->output;
        }

        if ($this->motionBlurEnabled) {
            $pipeline->addPass(new MotionBlurPass(
                $this->motionBlurShader,
                $currentInput,
                $depthTexture,
                $this->previousViewProjection,
                $this->motionBlurStrength,
                $this->motionBlurSamples,
            ));
            $postData = $data->get(PostProcessData::class);
            $currentInput = $postData->output;

            // store current VP for next frame
            $cameraData = $data->get(CameraData::class);
            /** @var Mat4 $vp */
            $vp = $cameraData->projection * $cameraData->view;
            $this->previousViewProjection = $vp->copy();
        }

        return $currentInput;
    }
}
