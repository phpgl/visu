<?php

namespace VISU\Quickstart;

use ClanCats\Container\Container;
use GL\Math\Vec2;
use VISU\ECS\EntityRegistry as EntityRegistry;
use VISU\Graphics\GLState;
use VISU\Graphics\RenderTarget;
use VISU\Graphics\TextureOptions;
use VISU\Graphics\Rendering\Renderer\FullscreenTextureRenderer;
use VISU\Graphics\Rendering\Pass\{BackbufferData, CallbackPass, ClearPass};
use VISU\Graphics\Rendering\{
    PipelineContainer, 
    PipelineResources, 
    RenderContext, 
    RenderPass, 
    RenderPipeline
};
use VISU\OS\Input;
use VISU\OS\{Window, WindowHints};
use VISU\Runtime\GameLoopDelegate;
use VISU\Signal\Dispatcher;
use VISU\OS\InputContextMap;
use VISU\Quickstart\Render\QuickstartDebugMetricsOverlay;

use GL\VectorGraphics\{VGContext, VGColor};
use VISU\ECS\SystemInterface;
use VISU\ECS\SystemRegistryTrait;
use VISU\FlyUI\FlyUI;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\ShaderCollection;
use VISU\Instrument\ProfilerInterface;
use VISU\Quickstart\Render\QuickstartPassData;

class QuickstartApp implements GameLoopDelegate
{
    /**
     * Our quickstart app is able to register systems directly
     */
    use SystemRegistryTrait;

    /**
     * GL State holder
     */
    public GLState $gl;

    /**
     * The window instance of the app
     */
    public Window $window;

    /**
     * A event dispacher instance 
     */
    public Dispatcher $dispatcher;

    /**
     * A shader collection instance
     */
    public ShaderCollection $shaders;

    /**
     * The input instance of the app
     */
    public Input $input;

    /**
     * An input action mapper
     */
    public InputContextMap $inputContext;

    /**
     * Rendering pipeline resources
     */
    public PipelineResources $renderResources;

    /**
     * An entity registry instance
     */
    public EntityRegistry $entities;

    /**
     * VectorGraphics Context
     */
    public VGContext $vg;

    /**
     * The current frame index
     */
    public int $frameIndex = 0;

    /**
     * The current tick index
     */
    public int $tickIndex = 0;

    /**
     * Fullscreen Texture Renderer
     */
    private FullscreenTextureRenderer $fullscreenTextureRenderer;

    /**
     * Quickstart Debug Metrics Overlay
     */
    private QuickstartDebugMetricsOverlay $dbgOverlayRenderer;

    /**
     * Optional Profiler
     */
    private ?ProfilerInterface $profiler = null;

    /**
     * QuickstartApp constructor.
     * 
     * @param Container $container 
     * @return void 
     */
    public function __construct(
        public Container $container,
        public QuickstartOptions $options,
    )
    {
        $getOrCreateService = function(string $name, callable $creator) : mixed {
            if (!$this->container->has($name)) {
                $this->container->set($name, $creator());
            }
            return $this->container->get($name);
        };

        // fetch the GL state from the container or create a new one
        $this->gl = $getOrCreateService('gl', function() {
            return new GLState();
        });

        // fetch the main dispatcher from the container or create a new one
        $this->dispatcher = $getOrCreateService('visu.dispatcher', function() {
            return new Dispatcher();
        });
        // dispatcher alias
        $this->container->alias('dispatcher', 'visu.dispatcher');

        // fetch or create the shader collection
        $this->shaders = $getOrCreateService('shaders', function() {
            $shaders = new ShaderCollection($this->gl, $this->container->getParameter('visu.path.resources.shader'));
            
            $shaders->enableVISUIncludes();
            $shaders->addVISUShaders();
            $shaders->scanShaderDirectory();

            return $shaders;
        });

        // create & initialize the window
        $windowHints = new WindowHints();
        if ($options->windowHeadless) {
            $windowHints->setVisible(false);
            $windowHints->setResizable(false);
            $windowHints->setFocusOnShow(false);
            
            $windowHints->setCocoaRetinaFramebuffer(false);
            $windowHints->setScaleToMonitor(false);
        } else {
            $windowHints->setFocusOnShow(true);
            $windowHints->setResizable(true);
        }

        $this->window = new Window($options->windowTitle, $options->windowWidth, $options->windowHeight, $windowHints);
        $this->container->set('window', $this->window);
        $this->window->initailize($this->gl);

        if ($options->windowVsync) {
            $this->window->setSwapInterval(1);
        } else {
            $this->window->setSwapInterval(0);
        }

        // create the input instance
        $this->input = $getOrCreateService('input', fn() => new Input($this->window, $this->dispatcher));

        // create the input action mapper
        $this->inputContext = new InputContextMap($this->dispatcher);

        // register the input as the windows main event handler
        $this->window->setEventHandler($this->input);

        // initialize the pipeline resources
        $this->renderResources = new PipelineResources($this->gl);

        // create the entity registry
        $this->entities = new EntityRegistry();

        // create the vector graphics context
        $this->vg = new VGContext(VGContext::ANTIALIAS);
        // rest GL state after creating the VG context as it might change some state
        $this->gl->reset();

        // initalize FlyUI
        FlyUI::initailize($this->vg, $this->dispatcher, $this->input);

        // create the fullscreen texture renderer
        $this->fullscreenTextureRenderer = new FullscreenTextureRenderer($this->gl);
        $this->dbgOverlayRenderer = new QuickstartDebugMetricsOverlay($this->container);
    }

    /**
     * A function that is invoked once the app is ready to run.
     * This happens exactly just before the game loop starts.
     * 
     * Here you can prepare your game state, register services, callbacks etc.
     */
    public function ready() : void
    {
        $this->options->ready?->__invoke($this);

        // auto register all system from the registry
        $this->registerSystems($this->entities);

        // run the scene initialization callback if available
        $this->options->initializeScene?->__invoke($this);
    }

    /**
     * Update the games state
     * This method might be called multiple times per frame, or not at all if
     * the frame rate is very high.
     * 
     * The update method should step the game forward in time, this is the place
     * where you would update the position of your game objects, check for collisions
     * and so on. 
     * 
     * @return void 
     */
    public function update() : void
    {
        // reset the input context for the next tick
        $this->inputContext->reset();

        // poll for new events
        $this->window->pollEvents();

        // run the update callback if available
        $this->options->update?->__invoke($this);

        // update the global tick index
        $this->tickIndex++;
    }

    /**
     * Render the current game state
     * This method is called once per frame.
     * 
     * The render method should draw the current game state to the screen. You recieve 
     * a delta time value which you can use to interpolate between the current and the
     * previous frame. This is useful for animations and other things that should be
     * smooth with variable frame rates.
     * 
     * @param float $deltaTime
     * @return void 
     */
    public function render(float $deltaTime) : void
    {
        $windowRenderTarget = $this->window->getRenderTarget();

        $data = new PipelineContainer;
        $pipeline = new RenderPipeline($this->renderResources, $data, $windowRenderTarget);
        $context = new RenderContext($pipeline, $data, $this->renderResources, $deltaTime);

        // create a data holder for the quickstart pass
        $quickstartPassData = $data->create(QuickstartPassData::class);

        // create an intermediate render target with a texture attachment
        $appContentScale = $windowRenderTarget->contentScaleX;
        $quickstartPassData->renderTarget = $context->pipeline->createRenderTargetLike('quickstartTarget', $windowRenderTarget);

        // we want a depth and stencil buffer
        $quickstartPassData->renderTarget->createRenderbufferDepthStencil = true;

        // create a color attachment
        $sceneColorOptions = new TextureOptions;
        $sceneColorOptions->internalFormat = GL_RGBA;
        $sceneColorAtt = $context->pipeline->createColorAttachment($quickstartPassData->renderTarget, 'quickstartColor', $sceneColorOptions);

        // we plan to render the scene color buffer to the backbuffer
        $quickstartPassData->outputTexture = $sceneColorAtt;

        // begin a FlyUI frame
        FlyUI::beginFrame($quickstartPassData->renderTarget->effectiveSizeVec(), $appContentScale);
        
        // store the VG context in the pipeline container
        // this will allow subsystem to access the VG context as well
        $data->set($this->vg);

        // run the render callback if available
        $this->options->render?->__invoke($this, $context, $quickstartPassData->renderTarget);
        $this->setupDrawBefore($context, $quickstartPassData->renderTarget);

        $pipeline->addPass(new CallbackPass(
            'QuickstartApp::draw',
            function(RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data) {
                $pipeline->writes($pass, $data->get(QuickstartPassData::class)->renderTarget);
            },
            function(PipelineContainer $data, PipelineResources $resources) use($context) 
            {
                $quickstartPassData = $data->get(QuickstartPassData::class);

                $renderTarget = $resources->getRenderTarget($quickstartPassData->renderTarget);
                $renderTarget->preparePass();
                
                // begin the VectorGraphics frame
                $this->vg->beginFrame($renderTarget->effectiveWidth(), $renderTarget->effectiveHeight(), $renderTarget->contentScaleX);

                // main draw call
                $this->draw($context, $renderTarget);

                // end the FlyUI frame
                FlyUI::endFrame();
                
                // end the VectorGraphics frame
                $this->vg->endFrame();
                // because VG touches the GL state we need to reset it
                $this->gl->reset();
            }
        ));

        $this->setupDrawAfter($context, $quickstartPassData->renderTarget);

        // create a fullscreen quad render pass
        $backbuffer = $data->get(BackbufferData::class)->target;

        // always clear the backbuffer
        $pipeline->addPass(new ClearPass($backbuffer));

        // render the offscreen render target to the backbuffer
        $this->fullscreenTextureRenderer->attachPass($context->pipeline, $backbuffer, $quickstartPassData->outputTexture);
        
        // render debug text overlay on top
        $this->dbgOverlayRenderer->attachPass(
            $pipeline, 
            $this->renderResources,
            $this->profiler,
            $backbuffer, 
            $deltaTime
        );

        // execute the pipeline
        $pipeline->execute($this->frameIndex++, $this->profiler);

        // swap the winows back and front buffer
        $this->window->swapBuffers();
        $this->input->endFrame();
    }

    /**
     * Prepare / setup additional render passes before the quickstart draw pass 
     * This is an "setup" method meaning you should not emit any draw calls here, but 
     * rather add additional render passes to the pipeline.
     * 
     * @param RenderContext $context
     * @param RenderTargetResource $renderTarget
     * @return void 
     */
    public function setupDrawBefore(RenderContext $context, RenderTargetResource $renderTarget) : void
    {
    }

    /**
     * Prepare / setup additional render passes after the quickstart draw pass 
     * This is an "setup" method meaning you should not emit any draw calls here, but 
     * rather add additional render passes to the pipeline.
     * 
     * @param RenderContext $context
     * @param RenderTargetResource $renderTarget
     * @return void 
     */
    public function setupDrawAfter(RenderContext $context, RenderTargetResource $renderTarget) : void
    {
    }

    /**
     * Draw the scene. (You most definetly want to use this)
     * 
     * This is called from within the Quickstart render pass where the pipeline is already
     * prepared, a VG frame is also already started.
     * 
     * @param RenderContext $context
     * @param RenderTarget $renderTarget
     * @return void 
     */
    public function draw(RenderContext $context, RenderTarget $renderTarget) : void
    {
        $this->options->draw?->__invoke($this, $context, $renderTarget);
    }

    /**
     * Returns boolean indicating whether the game loop should stop.
     * 
     * This method is called once per frame and should return true if the game loop
     * should stop. This is useful if you want to quit the game after a certain amount
     * of time or if the player has lost all his lives etc..
     * 
     * @return bool 
     */
    public function shouldStop() : bool
    {
        return $this->window->shouldClose();   
    }

    /**
     * Attaches the given profiler to the app, the profiler will be passed to the rendering pipeline.
     */
    public function attachProfiler(?ProfilerInterface $profiler) : void
    {
        $this->profiler = $profiler;
    }

    /**
     * Loads a GPU Compat Profiler and attaches it to the app
     * 
     * The GPU Compat Profiler uses blocking glBeginQuery / glEndQuery calls
     * which are not optimal for performance but allows basic GPU profiling on all systems.
     * 
     * Just do not enable it in production builds ;)
     */
    public function loadCompatGPUProfiler() : void
    {
        $profiler = new \VISU\Instrument\CompatGPUProfiler();
        $profiler->enabled = true;
        $this->attachProfiler($profiler);
    }

    /**
     * Updates the given system
     */
    public function updateSystem(SystemInterface $system) : void
    {
        $system->update($this->entities);
    }

    /**
     * Renders the given system with the given render context
     */
    public function renderSystem(SystemInterface $system, RenderContext $context) : void
    {
        $system->render($this->entities, $context);
    }
}
