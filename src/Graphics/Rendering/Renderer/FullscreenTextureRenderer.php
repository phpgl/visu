<?php

namespace VISU\Graphics\Rendering\Renderer;

use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\FullscreenQuadPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\TextureResource;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\ShaderStage;

class FullscreenTextureRenderer
{   
    /**
     * The debug font shader program.
     */
    private ShaderProgram $shaderProgram;

    /**
     * Constructor 
     * 
     * @param GLState $glstate The current GL state.
     */
    public function __construct(
        private GLState $glstate,
    )
    {
        // create the shader program
        $this->shaderProgram = new ShaderProgram($glstate);

        // attach a simple vertex shader
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
        #version 330 core

        layout (location = 0) in vec3 aPos;
        layout (location = 1) in vec2 aTexCoord;

        out vec2 TexCoords;

        void main()
        {
            gl_Position = vec4(aPos, 1.0);
            TexCoords = aTexCoord;
        }
        GLSL));

        // also attach a simple fragment shader
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
        #version 330 core

        out vec4 FragColor;
        in vec2 TexCoords;

        uniform sampler2D u_texture;

        void main()
        {             
            FragColor = texture(u_texture, TexCoords);
        }
        GLSL));
        $this->shaderProgram->link();
    }

    /**
     * Attaches a render pass to the pipeline
     * 
     * @param RenderPipeline $pipeline 
     * @param array<DebugOverlayText> $texts 
     */
    public function attachPass(
        RenderPipeline $pipeline, 
        RenderTargetResource $renderTarget,
        TextureResource $texture,
    ) : void
    {
        $pipeline->addPass(new FullscreenQuadPass(
            $renderTarget,
            $texture,
            $this->shaderProgram,
        ));
    }
}
