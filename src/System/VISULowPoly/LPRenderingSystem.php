<?php

namespace VISU\System\VISULowPoly;

use GL\Math\{GLM, Quat, Vec2, Vec3};
use VISU\Component\DirectionalLightComponent;
use VISU\Component\VISULowPoly\DynamicRenderableModel;
use VISU\D3D;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\Picker\DevEntityPickerRenderInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\Transform;
use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\Pass\DeferredLightPass;
use VISU\Graphics\Rendering\Pass\GBufferGeometryPassInterface;
use VISU\Graphics\Rendering\Pass\GBufferPass;
use VISU\Graphics\Rendering\Pass\GBufferPassData;
use VISU\Graphics\Rendering\Pass\DeferredLightPassData;
use VISU\Graphics\Rendering\Pass\SSAOData;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Renderer\FullscreenDebugDepthRenderer;
use VISU\Graphics\Rendering\Renderer\FullscreenTextureRenderer;
use VISU\Graphics\Rendering\Renderer\SSAORenderer;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\ShaderCollection;
use VISU\Graphics\ShaderProgram;

class LPRenderingSystem implements SystemInterface, DevEntityPickerRenderInterface
{
    /**
     * Rendering debug mode 
     */
    const DEBUG_MODE_NONE = 0;
    const DEBUG_MODE_POSITION = 1;
    const DEBUG_MODE_VIEW_POSITION = 2;
    const DEBUG_MODE_NORMALS = 3;
    const DEBUG_MODE_DEPTH = 4;
    const DEBUG_MODE_ALBEDO = 5;
    const DEBUG_MODE_SSAO = 6;
    public int $debugMode = self::DEBUG_MODE_NONE;

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

    /**
     * Screen Space Ambient Occlusion (SSAO) Renderer
     */
    private SSAORenderer $ssaoRenderer;

    /**
     * Shader programs
     */
    private ShaderProgram $objectShader;
    private ShaderProgram $devPickingShader;
    private ShaderProgram $lightingShader;

    /**
     * Constructor
     */
    public function __construct(
        private GLState $gl,
        private ShaderCollection $shaders,
        private LPModelCollection $modelCollection,
    )
    {
        $this->fullscreenRenderer = new FullscreenTextureRenderer($this->gl);
        $this->fullscreenDebugDepthRenderer = new FullscreenDebugDepthRenderer($this->gl);
        $this->ssaoRenderer = new SSAORenderer($this->gl, $this->shaders);

        // load the required shaders
        $this->objectShader = $this->shaders->get('visu/lowpoly/deferred_single_mesh');
        $this->devPickingShader = $this->shaders->get('visu/lowpoly/devpicking');
        $this->lightingShader = $this->shaders->get('visu/lowpoly/deferred_lightpass');
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

        // create single directional light
        $entities->setSingleton(new DirectionalLightComponent);
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
        // all dynamic renderables need an up to date aabb
        foreach($entities->view(DynamicRenderableModel::class) as $entity => $renerable) 
        {
            
        }
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

                    $this->objectShader->setUniformMatrix4f('model', false, $transform->getWorldMatrix($entities));

                    if (!isset($this->modelCollection->models[$renderable->modelName])) {
                        throw new \Exception('Model not found: ' . $renderable->modelName);
                    }

                    // render each mesh 
                    foreach($this->modelCollection->models[$renderable->modelName]->meshes as $mesh) 
                    {
                        $mesh->vertexBuffer->bind();
                        $this->objectShader->setUniformVec3('color', $mesh->material->color);
                        
                        glDrawArrays(GL_TRIANGLES, $mesh->vertexOffset, $mesh->vertexCount);
                    }
                }
            }
        ));

        // depending on the debug mode we pass some gbuffer textures 
        // directly to our target framebuffer and exit this pass
        if ($this->debugMode === self::DEBUG_MODE_NORMALS) {
            $this->fullscreenRenderer->attachPass($context->pipeline, $this->currentRenderTargetRes, $gbuffer->normalTexture);
            return;
        }
        elseif ($this->debugMode === self::DEBUG_MODE_POSITION) {
            $this->fullscreenRenderer->attachPass($context->pipeline, $this->currentRenderTargetRes, $gbuffer->worldSpacePositionTexture);
            return;
        }
        elseif ($this->debugMode === self::DEBUG_MODE_VIEW_POSITION) {
            $this->fullscreenRenderer->attachPass($context->pipeline, $this->currentRenderTargetRes, $gbuffer->viewSpacePositionTexture);
            return;
        }
        elseif ($this->debugMode === self::DEBUG_MODE_DEPTH) {
            $this->fullscreenDebugDepthRenderer->attachPass($context->pipeline, $this->currentRenderTargetRes, $gbuffer->depthTexture);
            return;
        }
        elseif ($this->debugMode === self::DEBUG_MODE_ALBEDO) {
            $this->fullscreenRenderer->attachPass($context->pipeline, $this->currentRenderTargetRes, $gbuffer->albedoTexture);
            return;
        }

        // make ssao pass
        $this->ssaoRenderer->attachPass($context->pipeline);
        $ssaoData = $context->data->get(SSAOData::class);
        
        if ($this->debugMode === self::DEBUG_MODE_SSAO) {
            $this->fullscreenRenderer->attachPass($context->pipeline, $this->currentRenderTargetRes, $ssaoData->blurTexture, true);
            return;
        }

        // add a light pass
        $context->pipeline->addPass(new DeferredLightPass(
            $this->lightingShader,
            $entities->getSingleton(DirectionalLightComponent::class)
        ));

        // read the light pass data
        $lightpass = $context->data->get(DeferredLightPassData::class);

        // copy over to the main render target
        $this->fullscreenRenderer->attachPass($context->pipeline, $this->currentRenderTargetRes, $lightpass->output);

        // reset the render target
        $this->currentRenderTargetRes = null;
    }


    /**
     * Renders all entites to a picking framebuffer 
     * 
     * @param EntitiesInterface $entities 
     * @param CameraData $cameraData 
     * @return void 
     */
    public function renderEntityIdsForPicking(EntitiesInterface $entities, CameraData $cameraData) : void
    {
        glEnable(GL_DEPTH_TEST);

        $this->devPickingShader->use();
        $this->devPickingShader->setUniformMat4('projection', false, $cameraData->projection);
        $this->devPickingShader->setUniformMat4('view', false, $cameraData->view);

        foreach($entities->view(DynamicRenderableModel::class) as $entity => $renderable) 
        {
            $transform = $entities->get($entity, Transform::class);
            $this->devPickingShader->setUniformMatrix4f('model', false, $transform->getWorldMatrix($entities));
            $this->devPickingShader->setUniform1i('entity_id', $entity);

            if (!isset($this->modelCollection->models[$renderable->modelName])) {
                throw new \Exception('Model not found: ' . $renderable->modelName);
            }

            // render each mesh 
            foreach($this->modelCollection->models[$renderable->modelName]->meshes as $mesh) 
            {
                $mesh->vertexBuffer->bind();
                glDrawArrays(GL_TRIANGLES, $mesh->vertexOffset, $mesh->vertexCount);
            }
        }
    }
}