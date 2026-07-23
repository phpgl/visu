<?php

namespace VISU\Graphics\Rendering\Renderer;

use VISU\Graphics\Rendering\Pass\CubemapPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\CubemapResource;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\TextureResource;
use VISU\Graphics\ShaderCollection;
use VISU\Graphics\ShaderProgram;

class CubemapRenderer
{
    /**
     * Standard skybox shader program
     */
    private ShaderProgram $skyboxShaderProgram;

    /**
     * Constructor
     *
     * @param ShaderCollection $shaders The shader collection to load the skybox shader from.
     */
    public function __construct(
        ShaderCollection $shaders,
    )
    {
        // load the skybox shader from the collection so it shares the same
        // tone mapping include as the rest of the scene (visu/skybox.{vert,frag}.glsl)
        $this->skyboxShaderProgram = $shaders->get('visu/skybox');
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