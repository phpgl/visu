<?php

namespace VISU\System\VISULowPoly;

use GL\Math\{GLM, Quat, Vec2, Vec3};
use VISU\Component\VISULowPoly\DynamicRenderableModel;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\Transform;
use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\Pass\GBufferGeometryPassInterface;
use VISU\Graphics\Rendering\Pass\GBufferPass;
use VISU\Graphics\Rendering\Pass\GBufferPassData;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Renderer\FullscreenDebugDepthRenderer;
use VISU\Graphics\Rendering\Renderer\FullscreenTextureRenderer;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\ShaderStage;

class LPRenderingSystem implements SystemInterface
{
    /**
     * The render target the renderer should render to
     */
    private ?RenderTargetResource $currentRenderTargetRes = null;

    /**
     * Array of geometry renderers, these are beeing invoked to generate the GBuffer
     * 
     * @var array<GBufferGeometryPassInterface>
     */
    private array $geometryRenderers = [];

    /**
     * Fullscreen Texture Debug Renderer
     */
    private FullscreenTextureRenderer $fullscreenRenderer;

    /**
     * Fullscreen Debug Depth Renderer
     */
    private FullscreenDebugDepthRenderer $fullscreenDebugDepthRenderer;

    private ShaderProgram $objectShader;

    /**
     * Constructor
     */
    public function __construct(
        private GLState $gl,
    )
    {
        $this->fullscreenRenderer = new FullscreenTextureRenderer($this->gl);
        $this->fullscreenDebugDepthRenderer = new FullscreenDebugDepthRenderer($this->gl);

        // create the terrain shader
        // create the shader program
        $this->objectShader = new ShaderProgram($this->gl);

        // attach a simple vertex shader
        $this->objectShader->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
        #version 330 core
        layout (location = 0) in vec3 a_position;
        layout (location = 1) in vec3 a_normal;

        out vec3 v_normal;
        out vec3 v_position;

        uniform mat4 projection;
        uniform mat4 view;
        uniform mat4 model;

        void main()
        {
            v_normal = a_normal;

            v_position = vec3(model * vec4(a_position, 1.0f));
            gl_Position = projection * view * model * vec4(a_position, 1.0f);
        }
        GLSL));

        // also attach a simple fragment shader
        $this->objectShader->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
        #version 330 core
        
        layout (location = 0) out vec3 gbuffer_position;
        layout (location = 1) out vec3 gbuffer_normal;
        layout (location = 2) out vec4 gbuffer_albedo;

        in vec3 v_normal;
        in vec3 v_position;

        uniform vec3 color;

        void main()
        {
            // basic phong lighting
            vec3 lightDir = normalize(vec3(0.0f, 1.0f, 1.0f));
            float diffuse = max(dot(v_normal, lightDir), 0.0f);

            gbuffer_albedo = vec4(color, 1.0f) * diffuse;
            gbuffer_normal = v_normal;
            gbuffer_position = v_position;
        }
        GLSL));
        $this->objectShader->link();
    }

    /**
     * Adds a geometry renderer to the system
     * 
     * @param GBufferGeometryPassInterface $renderer
     * @return void 
     */
    public function addGeometryRenderer(GBufferGeometryPassInterface $renderer) : void
    {
        $this->geometryRenderers[] = $renderer;
    }
    
    /**
     * Registers the system, this is where you should register all required components.
     * 
     * @return void 
     */
    public function register(EntitiesInterface $entities) : void
    {
        $entities->registerComponent(DynamicRenderableModel::class);
        $entities->registerComponent(Transform::class);
    }

    /**
     * Unregisters the system, this is where you can handle any cleanup.
     * 
     * @return void 
     */
    public function unregister(EntitiesInterface $entities) : void
    {
    }

    /**
     * Updates handler, this is where the game state should be updated.
     * 
     * @return void 
     */
    public function update(EntitiesInterface $entities) : void
    {
    }

    /**
     * Sets the render target the renderer should render to
     * !!! This has to be called every frame before calling render() !!!
     * 
     * @param RenderTargetResource $renderTargetRes 
     * @return void 
     */
    public function setRenderTarget(RenderTargetResource $renderTargetRes) : void
    {
        $this->currentRenderTargetRes = $renderTargetRes;
    }

    
    /**
     * Handles rendering of the scene, here you can attach additional render passes,
     * modify the render pipeline or customize rendering related data.
     * 
     * @param RenderContext $context
     */
    public function render(EntitiesInterface $entities, RenderContext $context) : void
    {
        if (is_null($this->currentRenderTargetRes)) {
            throw new \Exception('No render target set, call setRenderTarget() before calling render()');
        }

        // add the main GBuffer pass
        $context->pipeline->addPass(new GBufferPass);

        // read the GBuffer data
        $gbuffer = $context->data->get(GBufferPassData::class);

        foreach($this->geometryRenderers as $renderer) {
            $renderer->renderToGBuffer($entities, $context, $gbuffer);
        }

        // create a simple render pass for our models 
        // just to test if everything works @todo move this into seperate system
        $context->pipeline->addPass(new CallbackPass(
            // setup
            function(RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data) use($gbuffer) 
            {
                $pipeline->writes($pass, $gbuffer->renderTarget);
            },
            // execute
            function(PipelineContainer $data, PipelineResources $resources) use($entities) 
            {
                $cameraData = $data->get(CameraData::class);

                $this->objectShader->use();
                $this->objectShader->setUniformMatrix4f('projection', false, $cameraData->projection);
                $this->objectShader->setUniformMatrix4f('view', false, $cameraData->view);
                glEnable(GL_DEPTH_TEST);

                foreach($entities->view(DynamicRenderableModel::class) as $entity => $renderable) 
                {
                    $transform = $entities->get($entity, Transform::class);

                    $this->objectShader->setUniformMatrix4f('model', false, $transform->getLocalMatrix());

                    // render each mesh 
                    foreach($renderable->model->meshes as $mesh) 
                    {
                        $mesh->vertexBuffer->bind();
                        $this->objectShader->setUniformVec3('color', $mesh->material->color);
                        
                        glDrawArrays(GL_TRIANGLES, $mesh->vertexOffset, $mesh->vertexCount);
                    }
                }
            }
        ));

        $this->fullscreenRenderer->attachPass($context->pipeline, $this->currentRenderTargetRes, $gbuffer->albedoTexture);

        // reset the render target
        $this->currentRenderTargetRes = null;
    }
}