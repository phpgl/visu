<?php

namespace VISU\Graphics\Rendering\Renderer;

use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\CubemapPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\CubemapResource;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\TextureResource;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\ShaderStage;

class CubemapRenderer
{
    /**
     * Standard skybox shader program
     */
    private ShaderProgram $skyboxShaderProgram;

    /**
     * Constructor 
     * 
     * @param GLState $glstate The current GL state.
     */
    public function __construct(
        GLState $glstate,
    )
    {
        // create the skybox shader program
        $this->skyboxShaderProgram = new ShaderProgram($glstate);

        // attach skybox vertex shader
        $this->skyboxShaderProgram->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
        #version 330 core
        
        layout (location = 0) in vec3 a_pos;

        out vec3 tex_coords;

        uniform mat4 u_projection;
        uniform mat4 u_view;

        void main()
        {
            tex_coords = a_pos;
            // drop translation, we are in the skybox
            mat4 view = mat4(mat3(u_view)); 
            vec4 pos = u_projection * view * vec4(a_pos, 1.0);
            gl_Position = pos.xyww;
        }
        GLSL));

        // attach skybox fragment shader
        $this->skyboxShaderProgram->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
        #version 330 core
        
        out vec4 frag_color;

        in vec3 tex_coords;

        uniform samplerCube u_skybox;

        // simple reinhard tonemapping
        vec3 reinhard(vec3 color) {
            return color / (color + vec3(1.0));
        }

        // gamma correction
        vec3 gammaCorrect(vec3 color) {
            return pow(color, vec3(1.0/2.2));
        }

        void main()
        {    
            // vec3 hdrColor = textureLod(u_skybox, tex_coords, 0).rgb;
            vec3 hdrColor = texture(u_skybox, tex_coords).rgb;
            
            // apply tonemapping and gamma correction
            vec3 mapped = reinhard(hdrColor);
            vec3 gamma = gammaCorrect(mapped);
            
            frag_color = vec4(gamma, 1.0);
        }
        GLSL));

        $this->skyboxShaderProgram->link('skybox');
    }

    /**
     * Attaches a Skybox pass with the given cubemap
     */
    public function attachSkyboxPass(
        RenderPipeline $pipeline, 
        RenderTargetResource $renderTarget,
        CubemapResource $cubemap,
        ?TextureResource $depthTexture = null,
    ) : CubemapPass
    {
        $pass = new CubemapPass(
            $renderTarget,
            $cubemap,
            $this->skyboxShaderProgram,
            $depthTexture,
        );
        
        $pipeline->addPass($pass);
        
        return $pass;
    }
}