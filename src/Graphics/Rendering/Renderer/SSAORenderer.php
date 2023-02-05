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
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\TextureResource;
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
     * SSAO radius
     * this is the radius of the sphere that is used to sample
     */
    private float $radius = 0.5;

    /**
     * SSAO bias
     * this is the bias that is used to prevent self occlusion
     */
    private float $bias = 0.025;

    /**
     * SSAO intensity
     * this is the intensity of the SSAO effect
     */
    private float $intensity = 5.0;

    /**
     * SSAO scale, this is the resolution scale of the SSAO pass
     */
    private float $scale = 1.0;

    /**
     * SSAO blur scale, this is the resolution scale of the SSAO blur pass
     */
    private float $blurScale = 1.0;

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
        $this->ssaoShaderProgram = $shaders->get('visu/ssao');
        $this->ssaoBlurShaderProgram = $shaders->get('visu/ssao_blur');

        $this->quad = new QuadVertexArray($gl);

        $this->generateNoiseTexture();
        $this->generateKernel();
    }

    private function generateNoiseTexture() : void
    {
        $noiseBuffer = new FloatBuffer();
        for ($i = 0; $i < 16; $i++) {
            $noiseBuffer->pushVec3(new Vec3(
                (rand() / getrandmax()) * 2.0 - 1.0,
                (rand() / getrandmax()) * 2.0 - 1.0,
                0.0
            ));
        }

        $this->noiseTexture = new Texture($this->gl, 'ssao_noise');
        $to = new TextureOptions;
        $to->width = 4;
        $to->height = 4;
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
        for ($i = 0; $i < 64; $i++) {
            $sample = new Vec3(
                (rand() / getrandmax()) * 2.0 - 1.0,
                (rand() / getrandmax()) * 2.0 - 1.0,
                (rand() / getrandmax())
            );
            $sample->normalize();

            $scale = $i / 64.0;
            $scale = $this->lerp(0.1, 1.0, $scale * $scale);

            $sample = $sample * $scale;

            $this->kernel->pushVec3($sample);
        }
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
            // setup
            function(RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data)
            {
                $ssaoData = $data->create(SSAOData::class);
                $gbufferData = $data->get(GBufferPassData::class);

                $downSscale = $this->scale;
                $downSscaleBlur = $this->blurScale;

                $ssaoData->ssaoTarget = $pipeline->createRenderTarget(
                    'ssao_pass', 
                    (int) ($gbufferData->renderTarget->width / $downSscale), 
                    (int) ($gbufferData->renderTarget->height / $downSscale)
                );
                
                $ssaoTextureOptions = new TextureOptions;
                $ssaoTextureOptions->dataFormat = GL_RED;
                $ssaoTextureOptions->dataType = GL_FLOAT;
                $ssaoTextureOptions->internalFormat = GL_R16F;
                $ssaoTextureOptions->minFilter = GL_LINEAR;
                $ssaoTextureOptions->magFilter = GL_LINEAR;
                $ssaoData->ssaoTexture = $pipeline->createColorAttachment($ssaoData->ssaoTarget, 'ssao_output', $ssaoTextureOptions);

                // blur render target 
                $ssaoData->blurTarget = $pipeline->createRenderTarget(
                    'ssao_blur_pass', 
                    (int) ($gbufferData->renderTarget->width / $downSscaleBlur), 
                    (int) ($gbufferData->renderTarget->height / $downSscaleBlur)
                );

                $ssaoBlurTextureOptions = new TextureOptions;
                $ssaoBlurTextureOptions->dataFormat = GL_RED;
                $ssaoBlurTextureOptions->dataType = GL_FLOAT;
                $ssaoBlurTextureOptions->internalFormat = GL_R16F;
                $ssaoBlurTextureOptions->minFilter = GL_LINEAR;
                $ssaoBlurTextureOptions->magFilter = GL_LINEAR;
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
                $this->ssaoShaderProgram->setUniform1f('radius', $this->radius);
                $this->ssaoShaderProgram->setUniform1f('bias', $this->bias);
                $this->ssaoShaderProgram->setUniform1f('strength', $this->intensity);

                $normalMatrix = $cameradData->view->copy();
                $normalMatrix->transpose();
                $normalMatrix->inverse();
                $this->ssaoShaderProgram->setUniformMat4('normal_matrix', false, $cameradData->view);
                $this->ssaoShaderProgram->setUniformVec3Array('samples', $this->kernel);
                
                foreach([
                    [$gbufferData->viewSpacePositionTexture, 'position'], // @todo, use world space position and reconstruct view space position in shader
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
