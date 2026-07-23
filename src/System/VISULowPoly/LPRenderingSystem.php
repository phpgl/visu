<?php

namespace VISU\System\VISULowPoly;

use GL\Buffer\FloatBuffer;
use GL\Math\{GLM, Quat, Vec2, Vec3};
use GL\Rendering\DrawCallAssembler;
use VISU\Component\DirectionalLightComponent;
use VISU\Component\VISULowPoly\LPDynamicModel;
use VISU\Component\VISULowPoly\LPStaticModel;
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
use VISU\Graphics\Rendering\Pass\IBLPrecomputePass;
use VISU\Graphics\Rendering\Pass\IBLPrecomputeData;
use VISU\Graphics\Rendering\Pass\IBLCache;
use VISU\Graphics\Rendering\Pass\DeferredLightPassPermutation;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Renderer\FullscreenDebugDepthRenderer;
use VISU\Graphics\Rendering\Renderer\FullscreenTextureRenderer;
use VISU\Graphics\Rendering\Renderer\SSAORenderer;
use VISU\Graphics\Rendering\Renderer\CubemapRenderer;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\CubemapResource;
use VISU\Graphics\ShaderCollection;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\Cubemap;
use VISU\Graphics\Texture;
use VISU\OS\Logger;
use VISU\Quickstart\Render\QuickstartDebugMetricsOverlay;

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
    const DEBUG_MODE_METALLIC = 7;
    const DEBUG_MODE_ROUGHNESS = 8;
    const DEBUG_MODE_EMISSIVE = 9;
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
     * Cubemap Renderer for environment mapping
     */
    private CubemapRenderer $cubemapRenderer;

    /**
     * The cubemap used for environment lighting and reflections
     */
    private ?Cubemap $environmentCubemap = null;

    /**
     * Cache for IBL precompute results to avoid expensive recomputation
     */
    private IBLCache $iblCache;

    /**
     * Should the environment cubemap be rendered as skybox?
     */
    public bool $renderSkybox = true;

    /**
     * Shader programs
     */
    private ShaderProgram $objectInstancedShader;
    private ShaderProgram $devPickingShader;

    /**
     * Image based lighting shaders
     */
    private ShaderProgram $iblIrradianceShader;
    private ShaderProgram $iblPrefilterShader;
    private ShaderProgram $iblBrdfShader;

    /**
     * onAttach callback handle "LPStaticModel"
     */
    private int $onAttachStaticModelHandle;

    /**
     * onDetach callback handle "LPStaticModel"
     */
    private int $onDetachStaticModelHandle;

    /**
     * onAttach callback handle "LPDynamicModel"
     */
    private int $onAttachDynamicModelHandle;


    /**
     * The static geomentry draw call assembler
     */
    private DrawCallAssembler $staticGeometryDCA;

    /**
     * Indicates whether the static geometry DCA needs to be rebuilt
     */
    private bool $staticGeometryIsDirty = true;

    /**
     * The dynamic geomentry draw call assembler
     */
    private DrawCallAssembler $dynamicGeometryDCA;

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
        $this->cubemapRenderer = new CubemapRenderer($this->shaders);
        $this->iblCache = new IBLCache();

        // load the required shaders
        $this->objectInstancedShader = $this->shaders->get('visu/lowpoly/deferred_instanced_mesh');
        $this->devPickingShader = $this->shaders->get('visu/lowpoly/devpicking');

        $this->iblIrradianceShader = $this->shaders->get('visu/pbr_v1/bake_irradiance');
        $this->iblPrefilterShader = $this->shaders->get('visu/pbr_v1/bake_prefiltered_env');
        $this->iblBrdfShader = $this->shaders->get('visu/pbr_v1/bake_brdf_lut');
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
        $entities->registerComponent(LPDynamicModel::class);
        $entities->registerComponent(LPStaticModel::class);
        $entities->registerComponent(Transform::class);

        // create single directional light
        $entities->setSingleton(new DirectionalLightComponent);

        // register attach/detach handlers for static models
        $this->onAttachStaticModelHandle = $entities->onAttach(LPStaticModel::class, [$this, 'handleAttachStaticModel']);
        $this->onDetachStaticModelHandle = $entities->onDetach(LPStaticModel::class, [$this, 'handleDetachStaticModel']);
        $this->onAttachDynamicModelHandle = $entities->onAttach(LPDynamicModel::class, [$this, 'handleAttachDynamicModel']);

        // construct the draw call assembler for static geometry
        $this->staticGeometryDCA = new DrawCallAssembler();

        // construct the draw call assembler for dynamic geometry
        $this->dynamicGeometryDCA = new DrawCallAssembler();
    }

    /**
     * Unregisters the system, this is where you can handle any cleanup.
     * 
     * @return void 
     */
    public function unregister(EntitiesInterface $entities) : void
    {
        $entities->releaseOnAttach($this->onAttachStaticModelHandle);
        $entities->releaseOnDetach($this->onDetachStaticModelHandle);
    }

    private function registerModelWithDCA(LPModel $model, DrawCallAssembler $dca, ?int &$meshDcaHandle) : void
    {
        // we render the lowpoly models as a single mesh instead of the multiple sub-meshes
        // which were used to color them differently. We pack that information into the vertex data.
        // so register all the meshes as one mesh if not already done
        if (is_null($meshDcaHandle)) {

            // we load the sub-meshes in sequence into the same VBO, so we really 
            // can just calculate the offsets and counts here
            $startOffset = 0;
            $vertexCount = 0;
            $fistMesh = null;
            foreach($model->meshes as $mesh) {
                if ($vertexCount === 0) {
                    $startOffset = $mesh->vertexOffset;
                    $fistMesh = $mesh;
                }

                $vertexCount += $mesh->vertexCount;
            }

            if (is_null($fistMesh)) {
                throw new LPException('LPRenderingSystem - Cannot register model, no meshes found: ' . $model->name);
            }

            $meshDcaHandle = $dca->registerMesh(
                vao: $fistMesh->vertexBuffer->getVertexArrayId(),
                vertexOffset: $startOffset,
                vertexCount: $vertexCount,
                indexOffset: 0,
                indexCount: 0,
                aabbMin: $model->aabb->min,
                aabbMax: $model->aabb->max,
                materialHint: 0,
                primitive: GL_TRIANGLES
            );

            $dca->bindTransformBuffer($fistMesh->vertexBuffer->getVertexArrayId(), 6);
        }
    }

    /**
     * Handles the attachment of a static model
     */
    public function handleAttachStaticModel(EntitiesInterface $entities, int $entity, LPStaticModel $component) : void
    {
        $this->staticGeometryIsDirty = true;

        // validate the model is present
        if (!isset($this->modelCollection->models[$component->modelIdentifier])) {
            Logger::warn('LPRenderingSystem - Model not found: ' . $component->modelIdentifier);
            return;
        }

        $model = $this->modelCollection->models[$component->modelIdentifier];

        $this->registerModelWithDCA($model, $this->staticGeometryDCA, $model->staticDCAHandle);

        if (!$transform = $entities->tryGet($entity, Transform::class)) {
            throw new LPException('LPRenderingSystem - Please attach a Transform component first, entity: ' . $entity);
        }

        // submit the instance
        $this->staticGeometryDCA->submit(
            meshHandle: $model->staticDCAHandle,
            transform: $transform->getWorldMatrix($entities),
            materialId: 1, // material data is packed into the vertex buffer.
        );
    }

    /**
     * Handles the detachment of a static model
     */
    public function handleDetachStaticModel(EntitiesInterface $entities, int $entity, LPStaticModel $component) : void
    {
        $this->staticGeometryIsDirty = true;
    }

    /**
     * Handles the attachment of a dynamic model
     */
    public function handleAttachDynamicModel(EntitiesInterface $entities, int $entity, LPDynamicModel $component) : void
    {
        // validate the model is present
        if (!isset($this->modelCollection->models[$component->modelIdentifier])) {
            Logger::warn('LPRenderingSystem - Model not found: ' . $component->modelIdentifier);
            return;
        }

        $model = $this->modelCollection->models[$component->modelIdentifier];

        $this->registerModelWithDCA($model, $this->dynamicGeometryDCA, $model->dynamicDCAHandle);
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
     * Sets the environment cubemap for rendering
     * 
     * @param Cubemap|null $cubemap The cubemap to use for environment lighting and reflections
     * @return void 
     */
    public function setEnvironmentCubemap(?Cubemap $cubemap) : void
    {
        // invalidate the cache when the cubemap changes
        if ($this->environmentCubemap !== $cubemap) {
            $this->iblCache->invalidate();
        }
        $this->environmentCubemap = $cubemap;
    }

    /**
     * Forces the IBL cache to be recomputed on the next frame
     * 
     * @return void
     */
    public function invalidateIBLCache() : void
    {
        $this->iblCache->invalidate();
    }
    
    /**
     * Gets the current environment cubemap
     * 
     * @return Cubemap|null The current environment cubemap
     */
    public function getEnvironmentCubemap() : ?Cubemap
    {
        return $this->environmentCubemap;
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

        $renderMetrics = [
            'static_draw_calls' => 0,
            'static_instances' => 0,
            'dynamic_draw_calls' => 0,
            'dynamic_instances' => 0,
        ];

        /**
         * Static Geometry Render Pass
         */
        $context->pipeline->addPass(new CallbackPass(
            'LPStaticMesh',
            // setup
            function(RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data) use($gbuffer) 
            {
                $pipeline->writes($pass, $gbuffer->renderTarget);
            },
            // execute
            function(PipelineContainer $data, PipelineResources $resources) use(&$renderMetrics) 
            {
                $cameraData = $data->get(CameraData::class);

                $this->objectInstancedShader->use();
                $this->objectInstancedShader->setUniformMatrix4f('projection', false, $cameraData->projection);
                $this->objectInstancedShader->setUniformMatrix4f('view', false, $cameraData->view);
                glEnable(GL_DEPTH_TEST);

                $this->staticGeometryDCA->setCameraData(
                    $cameraData->renderCamera->transform->position,
                    $cameraData->view,
                    $cameraData->projection
                );

                $this->staticGeometryDCA->execute(function(int $meshHandle, int $materialId, int $instanceOffset, int $instanceCount, int $flags) use(&$renderMetrics) {
                    $renderMetrics['static_draw_calls'] += 1;
                    $renderMetrics['static_instances'] += $instanceCount;
                });
            }
        ));

        /**
         * Dynamic Geometry Render Pass
         */
        $context->pipeline->addPass(new CallbackPass(
            'LPDynamicMesh',
            // setup
            function(RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data) use($gbuffer) 
            {
                $pipeline->writes($pass, $gbuffer->renderTarget);
            },
            // execute
            function(PipelineContainer $data, PipelineResources $resources) use($entities, &$renderMetrics)
            {
                $cameraData = $data->get(CameraData::class);

                $this->dynamicGeometryDCA->clearInstances();

                // fetch all dynamic models and submit their instances
                /** @var iterable<int, array{0: LPDynamicModel, 1: Transform}> $dynamicView */
                $dynamicView = $entities->viewWith(LPDynamicModel::class, Transform::class);
                foreach ($dynamicView as $entity => [$renderable, $transform]) {
                    if (!isset($this->modelCollection->models[$renderable->modelIdentifier])) {
                        continue;
                    }

                    $model = $this->modelCollection->models[$renderable->modelIdentifier];

                    // unregistered model
                    if (is_null($model->dynamicDCAHandle)) {
                        continue;
                    }

                    $this->dynamicGeometryDCA->submit(
                        meshHandle: $model->dynamicDCAHandle,
                        transform: $transform->getWorldMatrix($entities),
                        materialId: 1, // material data is packed into the vertex buffer.
                    );
                }
                
                $this->objectInstancedShader->use();
                $this->objectInstancedShader->setUniformMatrix4f('projection', false, $cameraData->projection);
                $this->objectInstancedShader->setUniformMatrix4f('view', false, $cameraData->view);
                glEnable(GL_DEPTH_TEST);

                $this->dynamicGeometryDCA->setCameraData(
                    $cameraData->renderCamera->transform->position,
                    $cameraData->view,
                    $cameraData->projection
                );

                $this->dynamicGeometryDCA->execute(function(int $meshHandle, int $materialId, int $instanceOffset, int $instanceCount, int $flags) use(&$renderMetrics) {
                    $renderMetrics['dynamic_draw_calls'] += 1;
                    $renderMetrics['dynamic_instances'] += $instanceCount;
                });
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
        elseif ($this->debugMode === self::DEBUG_MODE_METALLIC) {
            $this->fullscreenRenderer->attachPass($context->pipeline, $this->currentRenderTargetRes, $gbuffer->metallicTexture, true);
            return;
        }
        elseif ($this->debugMode === self::DEBUG_MODE_ROUGHNESS) {
            $this->fullscreenRenderer->attachPass($context->pipeline, $this->currentRenderTargetRes, $gbuffer->roughnessTexture, true);
            return;
        }
        elseif ($this->debugMode === self::DEBUG_MODE_EMISSIVE) {
            $this->fullscreenRenderer->attachPass($context->pipeline, $this->currentRenderTargetRes, $gbuffer->emissiveTexture);
            return;
        }

        // make ssao pass
        $this->ssaoRenderer->attachPass($context->pipeline);
        $ssaoData = $context->data->get(SSAOData::class);
        
        if ($this->debugMode === self::DEBUG_MODE_SSAO) {
            $this->fullscreenRenderer->attachPass($context->pipeline, $this->currentRenderTargetRes, $ssaoData->blurTexture, true);
            return;
        }

        // import cubemap if available
        $cubemapRes = null;
        if ($this->environmentCubemap) {
            $cubemapRes = $context->pipeline->importCubemap('environment_cubemap', $this->environmentCubemap);
        }

        if ($this->environmentCubemap !== null) {
            $context->pipeline->addPass(new IBLPrecomputePass(
                $cubemapRes,
                $this->iblIrradianceShader,
                $this->iblPrefilterShader,
                $this->iblBrdfShader,
                $this->iblCache,
                32,
                256,
                128,
            ));
        }

        // add a light pass
        $context->pipeline->addPass(new DeferredLightPass(
            $this->shaders,
            $entities->getSingleton(DirectionalLightComponent::class),
            $cubemapRes,
        ));

        // read the light pass data
        $lightpass = $context->data->get(DeferredLightPassData::class);
    
        // add a skybox cubemap pass
        if ($this->renderSkybox && $cubemapRes) {
            $this->cubemapRenderer->attachSkyboxPass(
                $context->pipeline,
                $lightpass->renderTarget, 
                $cubemapRes,
                $gbuffer->depthTexture 
            );
        }

        // copy over to the main render target
        $this->fullscreenRenderer->attachPass($context->pipeline, $this->currentRenderTargetRes, $lightpass->output);

        // reset the render target
        $this->currentRenderTargetRes = null;
    }

    /**
     * Renders the static geometry ("LPStaticModel" components)
     */
    public function renderStaticGeometry(EntitiesInterface $entities, RenderContext $context, GBufferPassData $gbufferData) : void
    {

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

        foreach($entities->view(LPDynamicModel::class) as $entity => $renderable) 
        {
            $transform = $entities->get($entity, Transform::class);
            $this->devPickingShader->setUniformMatrix4f('model', false, $transform->getWorldMatrix($entities));
            $this->devPickingShader->setUniform1i('entity_id', $entity);

            if (!isset($this->modelCollection->models[$renderable->modelIdentifier])) {
                throw new \Exception('Model not found: ' . $renderable->modelIdentifier);
            }

            // render each mesh 
            foreach($this->modelCollection->models[$renderable->modelIdentifier]->meshes as $mesh) 
            {
                $mesh->vertexBuffer->bind();
                glDrawArrays(GL_TRIANGLES, $mesh->vertexOffset, $mesh->vertexCount);
            }
        }
    }
}