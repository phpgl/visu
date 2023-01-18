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
     * Just renders a texture
     */
    private ShaderProgram $shaderProgram;

    /**
     * The same but in monochrome
     */
    private ShaderProgram $monochromeShaderProgram;

    /**
     * Constructor 
     * 
     * @param GLState $glstate The current GL state.
     */
    public function __construct(
        GLState $glstate,
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

        // Monochrome shader
        $this->monochromeShaderProgram = new ShaderProgram($glstate);

        // attach a simple vertex shader
        $this->monochromeShaderProgram->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
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
        $this->monochromeShaderProgram->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
        #version 330 core

        out vec4 FragColor;
        in vec2 TexCoords;

        uniform sampler2D u_texture;
        void main()
        {             
            FragColor = vec4(vec3(texture(u_texture, TexCoords).r), 1.0);
        }
        GLSL));
        $this->monochromeShaderProgram->link();
    }

    /**
     * Attaches a render pass to the pipeline
     * 
     * @param RenderPipeline $pipeline 
     * @param RenderTargetResource $renderTarget
     * @param TextureResource $texture
     */
    public function attachPass(
        RenderPipeline $pipeline, 
        RenderTargetResource $renderTarget,
        TextureResource $texture,
        bool $monochrome = false,
    ) : void
    {
        $pipeline->addPass(new FullscreenQuadPass(
            $renderTarget,
            $texture,
            $monochrome ? $this->monochromeShaderProgram : $this->shaderProgram,
        ));
    }
}
