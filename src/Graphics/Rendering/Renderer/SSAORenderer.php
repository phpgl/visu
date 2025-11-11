<?php

namespace VISU\Graphics\Rendering\Renderer;

use GL\Buffer\FloatBuffer;
use GL\Math\Mat4;
use GL\Math\Vec3;
use VISU\Graphics\GLState;
use VISU\Graphics\QuadVertexArray;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\Pass\GBufferPassData;
use VISU\Graphics\Rendering\Pass\SSAOData;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\ShaderCollection;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\Texture;
use VISU\Graphics\TextureOptions;

class SSAORenderer
{   
    /**
     * SSAO shader program
     */
    private ShaderProgram $ssaoShaderProgram;

    /**
     * SSAO blur shader program
     */
    private ShaderProgram $ssaoBlurShaderProgram;
    
    /**
     * Noise texture for SSAO 
     */
    private Texture $noiseTexture;

    /**
     * Quad vertex array
     */
    private QuadVertexArray $quad;

    /**
     * Kernel for SSAO
     */
    private FloatBuffer $kernel;

    /**
     * Current SSAO quality configuration
     */
    private SSAOQuality $currentQuality;

    /**
     * The maximum number of samples supported by the SSAO shader
     */
    private const MAX_SAMPLES = 64;

    /**
     * The noise texture size
     */
    private const NOISE_TEXTURE_SIZE = 4;

    /**
     * Constructor 
     * 
     * @param GLState $gl The current GL state.
     * @param ShaderCollection $shaders The shader collection to use.
     */
    public function __construct(
        private GLState $gl,
        ShaderCollection $shaders
    )
    {
        $shaders->setGlobalDefine('SSAO_MAX_SAMPLES', self::MAX_SAMPLES);
        $shaders->setGlobalDefine('SSAO_NOISE_TEXTURE_SIZE', self::NOISE_TEXTURE_SIZE);
        $this->ssaoShaderProgram = $shaders->get('visu/ssao');
        $this->ssaoBlurShaderProgram = $shaders->get('visu/ssao_blur');

        $this->quad = new QuadVertexArray($gl);

        $this->setQuality(SSAOQuality::ultra());

        $this->generateNoiseTexture();
        $this->generateKernel();
    }

    private function generateNoiseTexture() : void
    {
        $noiseBuffer = new FloatBuffer();
        for ($i = 0; $i < self::NOISE_TEXTURE_SIZE * self::NOISE_TEXTURE_SIZE; $i++) {
            // generate normalized random vectors for rotation
            $noiseBuffer->pushVec3(new Vec3(
                (rand() / getrandmax()) * 2.0 - 1.0,
                (rand() / getrandmax()) * 2.0 - 1.0,
                (rand() / getrandmax()) * 2.0 - 1.0
            ));
        }

        $this->noiseTexture = new Texture($this->gl, 'ssao_noise');
        $to = new TextureOptions;
        $to->width = self::NOISE_TEXTURE_SIZE;
        $to->height = self::NOISE_TEXTURE_SIZE;
        $to->internalFormat = GL_RGB32F;
        $to->dataFormat = GL_RGB;
        $to->dataType = GL_FLOAT;
        $to->minFilter = GL_NEAREST;
        $to->magFilter = GL_NEAREST;
        $to->wrapS = GL_REPEAT;
        $to->wrapT = GL_REPEAT;
        $to->generateMipmaps = false;
        $this->noiseTexture->uploadBuffer($to, $noiseBuffer);
    }

    private function lerp(float $a, float $b, float $f) : float
    {
        return $a + $f * ($b - $a);
    }

    private function generateKernel() : void
    {
        $this->kernel = new FloatBuffer;

        for ($i = 0; $i < self::MAX_SAMPLES; $i++) {
            $sample = new Vec3(
                (rand() / getrandmax()) * 2.0 - 1.0,
                (rand() / getrandmax()) * 2.0 - 1.0,
                (rand() / getrandmax())
            );
            $sample->normalize();

            $scale = $i / self::MAX_SAMPLES;
            $scale = $this->lerp(0.1, 1.0, $scale * $scale);

            $sample = $sample * $scale;

            $this->kernel->pushVec3($sample);
        }
    }

    /**
     * Set SSAO quality configuration
     */
    public function setQuality(SSAOQuality $quality): void
    {
        $this->currentQuality = $quality;
    }

    /**
     * Get current quality configuration
     */
    public function getCurrentQuality(): SSAOQuality
    {
        return $this->currentQuality;
    }

    /**
     * Get current quality name
     */
    public function getCurrentQualityName(): string
    {
        return $this->currentQuality->name;
    }

    /**
     * Attaches a render pass to the pipeline
     * 
     * @param RenderPipeline $pipeline 
     */
    public function attachPass(
        RenderPipeline $pipeline, 
    ) : void
    {
        $pipeline->addPass(new CallbackPass(
            'SSAO',
            // setup
            function(RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data)
            {
                $ssaoData = $data->create(SSAOData::class);
                $gbufferData = $data->get(GBufferPassData::class);

                $downScale = max(0.01, $this->currentQuality->scale);
                $downScaleBlur = max(0.01, $this->currentQuality->blurScale);

                $ssaoData->ssaoTarget = $pipeline->createRenderTarget(
                    'ssao_pass', 
                    (int) ($gbufferData->renderTarget->width * $downScale),
                    (int) ($gbufferData->renderTarget->height * $downScale)
                );
                
                $ssaoTextureOptions = new TextureOptions;
                $ssaoTextureOptions->dataFormat = GL_RED;
                $ssaoTextureOptions->dataType = GL_FLOAT;
                $ssaoTextureOptions->internalFormat = GL_R16F;
                $ssaoTextureOptions->minFilter = GL_LINEAR;
                $ssaoTextureOptions->magFilter = GL_LINEAR;
                $ssaoTextureOptions->wrapS = GL_CLAMP_TO_EDGE;
                $ssaoTextureOptions->wrapT = GL_CLAMP_TO_EDGE;
                $ssaoData->ssaoTexture = $pipeline->createColorAttachment($ssaoData->ssaoTarget, 'ssao_output', $ssaoTextureOptions);

                // blur render target 
                $ssaoData->blurTarget = $pipeline->createRenderTarget(
                    'ssao_blur_pass', 
                    (int) ($gbufferData->renderTarget->width * $downScaleBlur),
                    (int) ($gbufferData->renderTarget->height * $downScaleBlur)
                );

                $ssaoBlurTextureOptions = new TextureOptions;
                $ssaoBlurTextureOptions->dataFormat = GL_RED;
                $ssaoBlurTextureOptions->dataType = GL_FLOAT;
                $ssaoBlurTextureOptions->internalFormat = GL_R16F;
                $ssaoBlurTextureOptions->minFilter = GL_LINEAR;
                $ssaoBlurTextureOptions->magFilter = GL_LINEAR;
                $ssaoBlurTextureOptions->wrapS = GL_CLAMP_TO_EDGE;
                $ssaoBlurTextureOptions->wrapT = GL_CLAMP_TO_EDGE;
                $ssaoData->blurTexture = $pipeline->createColorAttachment($ssaoData->blurTarget, 'ssao_blur_output', $ssaoBlurTextureOptions);
            },
            // execute
            function(PipelineContainer $data, PipelineResources $resources) 
            {
                $ssaoData = $data->get(SSAOData::class);
                $gbufferData = $data->get(GBufferPassData::class);
                $cameradData = $data->get(CameraData::class);

                $resources->activateRenderTarget($ssaoData->ssaoTarget);

                $this->ssaoShaderProgram->use();
                $this->ssaoShaderProgram->setUniformIvec2('screen_size', $ssaoData->ssaoTarget->width, $ssaoData->ssaoTarget->height);
                $this->ssaoShaderProgram->setUniformMat4('projection', false, $cameradData->projection);
                
                // calculate and set inverse projection matrix
                $inverseProjection = $cameradData->projection->copy();
                $inverseProjection->inverse();
                $this->ssaoShaderProgram->setUniformMat4('inverse_projection', false, $inverseProjection);
                
                $this->ssaoShaderProgram->setUniform1f('radius', $this->currentQuality->radius);
                $this->ssaoShaderProgram->setUniform1f('bias', $this->currentQuality->bias);
                $this->ssaoShaderProgram->setUniform1f('strength', $this->currentQuality->strength);
                $this->ssaoShaderProgram->setUniform1i('sample_count', $this->currentQuality->sampleCount);

                $normalMatrix = $cameradData->view->copy();
                $normalMatrix->transpose();
                $normalMatrix->inverse();
                $this->ssaoShaderProgram->setUniformMat4('normal_matrix', false, $cameradData->view);
                $this->ssaoShaderProgram->setUniformVec3Array('samples', $this->kernel);
                
                foreach([
                    [$gbufferData->depthTexture, 'depth'],
                    [$gbufferData->normalTexture, 'normal'],
                ] as $i => $tuple) {
                    list($texture, $name) = $tuple;
                    $glTexture = $resources->getTexture($texture);
                    $glTexture->bind(GL_TEXTURE0 + $i);
                    $this->ssaoShaderProgram->setUniform1i('gbuffer_' . $name, $i);
                }

                $this->ssaoShaderProgram->setUniform1i('noise_texture', 2);
                $this->noiseTexture->bind(GL_TEXTURE2);

                $this->quad->draw();

                $resources->activateRenderTarget($ssaoData->blurTarget);

                $ssaoTexture = $resources->getTexture($ssaoData->ssaoTexture);

                $this->ssaoBlurShaderProgram->use();
                $this->ssaoBlurShaderProgram->setUniform1i('ssao_noisy', 0);
                $ssaoTexture->bind(GL_TEXTURE0);
                $this->quad->draw();
            }
        ));
    }
}
