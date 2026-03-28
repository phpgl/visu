<?php

namespace VISU\Graphics\Rendering\Renderer;

use GL\Buffer\FloatBuffer;
use VISU\Component\ParticleEmitterComponent;
use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\ShaderProgram;
use VISU\System\ParticleSystem;

class ParticleRenderer
{
    /**
     * Instance data stride: pos(3) + color(4) + size(1) = 8 floats
     */
    const INSTANCE_STRIDE = 8;
    const INSTANCE_STRIDE_BYTES = 32; // 8 * 4

    private int $quadVAO = 0;
    private int $quadVBO = 0;
    private int $instanceVBO = 0;

    private bool $initialized = false;

    public function __construct(
        private GLState $gl,
        private ShaderProgram $shader,
    ) {
    }

    private function initGLResources(): void
    {
        if ($this->initialized) {
            return;
        }

        // quad vertices: 2D positions for a billboard quad
        $quadData = new FloatBuffer([
            -0.5, -0.5,
             0.5, -0.5,
            -0.5,  0.5,
             0.5, -0.5,
             0.5,  0.5,
            -0.5,  0.5,
        ]);

        glGenVertexArrays(1, $this->quadVAO);
        glGenBuffers(1, $this->quadVBO);
        glGenBuffers(1, $this->instanceVBO);

        $this->gl->bindVertexArray($this->quadVAO);

        // quad vertex buffer (location 0)
        $this->gl->bindVertexArrayBuffer($this->quadVBO);
        glBufferData(GL_ARRAY_BUFFER, $quadData, GL_STATIC_DRAW);
        glVertexAttribPointer(0, 2, GL_FLOAT, false, 2 * GL_SIZEOF_FLOAT, 0);
        glEnableVertexAttribArray(0);

        // instance buffer (locations 1-3)
        glBindBuffer(GL_ARRAY_BUFFER, $this->instanceVBO);

        // position (vec3) at location 1
        glVertexAttribPointer(1, 3, GL_FLOAT, false, self::INSTANCE_STRIDE_BYTES, 0);
        glEnableVertexAttribArray(1);
        glVertexAttribDivisor(1, 1);

        // color (vec4) at location 2
        glVertexAttribPointer(2, 4, GL_FLOAT, false, self::INSTANCE_STRIDE_BYTES, 3 * GL_SIZEOF_FLOAT);
        glEnableVertexAttribArray(2);
        glVertexAttribDivisor(2, 1);

        // size (float) at location 3
        glVertexAttribPointer(3, 1, GL_FLOAT, false, self::INSTANCE_STRIDE_BYTES, 7 * GL_SIZEOF_FLOAT);
        glEnableVertexAttribArray(3);
        glVertexAttribDivisor(3, 1);

        $this->initialized = true;
    }

    /**
     * Attaches a particle render pass to the pipeline.
     * Renders all active particle emitters as billboarded quads.
     */
    public function attachPass(
        RenderPipeline $pipeline,
        RenderTargetResource $renderTarget,
        ParticleSystem $particleSystem,
    ): void {
        $pipeline->addPass(new CallbackPass(
            'ParticleRender',
            function (RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data) use ($renderTarget) {
                $pipeline->writes($pass, $renderTarget);
            },
            function (PipelineContainer $data, PipelineResources $resources) use ($renderTarget, $particleSystem) {
                $this->initGLResources();

                $cameraData = $data->get(CameraData::class);
                $resources->activateRenderTarget($renderTarget);

                $this->shader->use();
                $this->shader->setUniformMatrix4f('u_view', false, $cameraData->view);
                $this->shader->setUniformMatrix4f('u_projection', false, $cameraData->projection);

                glEnable(GL_DEPTH_TEST);
                glDepthMask(false); // don't write to depth buffer
                glEnable(GL_BLEND);

                $this->gl->bindVertexArray($this->quadVAO);

                foreach ($particleSystem->getPools() as $entity => $pool) {
                    if ($pool->aliveCount === 0) {
                        continue;
                    }

                    $instanceBuffer = $pool->buildInstanceBuffer();

                    // upload instance data
                    glBindBuffer(GL_ARRAY_BUFFER, $this->instanceVBO);
                    glBufferData(GL_ARRAY_BUFFER, $instanceBuffer, GL_DYNAMIC_DRAW);

                    // determine blend mode from emitter (if still exists)
                    // default to alpha blending
                    glBlendFunc(GL_SRC_ALPHA, GL_ONE_MINUS_SRC_ALPHA);

                    $this->shader->setUniform1i('u_has_texture', 0);

                    // instanced draw: 6 vertices per quad, N instances
                    glDrawArraysInstanced(GL_TRIANGLES, 0, 6, $pool->aliveCount);
                }

                glDepthMask(true);
                glDisable(GL_BLEND);
            }
        ));
    }
}
