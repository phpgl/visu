<?php

namespace VISU\Quickstart;

use ClanCats\Container\Container;
use VISU\ECS\EntityRegisty;
use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\BackbufferData;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\OS\Input;
use VISU\OS\Window;
use VISU\OS\WindowHints;
use VISU\Runtime\GameLoopDelegate;
use VISU\Signal\Dispatcher;

use GL\VectorGraphics\{VGContext, VGColor};

class QuickstartApp implements GameLoopDelegate
{
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
     * The input instance of the app
     */
    public Input $input;

    /**
     * Rendering pipeline resources
     */
    public PipelineResources $renderResources;

    /**
     * An entity registry instance
     */
    public EntityRegisty $entities;

    /**
     * VectorGraphics Context
     */
    public VGContext $vg;

    /**
     * The current frame index
     */
    public int $frameIndex = 0;

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
        // create GL state helper 
        $this->gl = new GLState();
        $this->container->set('gl', $this->gl);

        // create the event dispatcher
        $this->dispatcher = new Dispatcher();
        $this->container->set('dispatcher', $this->dispatcher);

        // create & initialize the window
        $windowHints = new WindowHints();
        $windowHints->setFocusOnShow(true);
        $windowHints->setResizable(true);

        $this->window = new Window($options->windowTitle, $options->windowWidth, $options->windowHeight, $windowHints);
        $this->container->set('window', $this->window);
        $this->window->initailize($this->gl);

        if ($options->windowVsync) {
            $this->window->setSwapInterval(1);
        }

        // create the input instance
        $this->input = new Input($this->window, $this->dispatcher);

        // register the input as the windows main event handler
        $this->window->setEventHandler($this->input);

        // initialize the pipeline resources
        $this->renderResources = new PipelineResources($this->gl);

        // create the entity registry
        $this->entities = new EntityRegisty();

        // create the vector graphics context
        $this->vg = new VGContext(VGContext::ANTIALIAS);
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
        // poll for new events
        $this->window->pollEvents();

        // run the update callback if available
        $this->options->update?->__invoke($this);
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

        // backbuffer render target
        $backbuffer = $data->get(BackbufferData::class)->target;

        // run the render callback if available
        $this->options->render?->__invoke($this, $context);

        $pipeline->addPass(new CallbackPass(
            'QuickstartApp::draw',
            function(RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data) use($backbuffer) {
                $pipeline->writes($pass, $backbuffer);
            },
            function(PipelineContainer $data, PipelineResources $resources) use($backbuffer, $context) 
            {
                $renderTarget = $resources->getRenderTarget($backbuffer);
                $renderTarget->preparePass();

                $this->vg->beginFrame(
                    $renderTarget->width() / $renderTarget->contentScaleX, 
                    $renderTarget->height() /$renderTarget->contentScaleX, 
                    $renderTarget->contentScaleX
                );
                $this->options->draw?->__invoke($this, $context, $renderTarget);

                $this->vg->endFrame();
            }
        ));
        
        // render debug text
        // $this->dbg3D->attachPass($pipeline, $backbuffer);
        // $this->dbgText->attachPass($pipeline, $this->pipelineResources, $backbuffer, $deltaTime);

        // execute the pipeline
        $pipeline->execute($this->frameIndex++, null);

        // swap the winows back and front buffer
        $this->window->swapBuffers();
    }

    /**
     * Loop should stop
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
}