<?php

namespace VISU\Quickstart;

use ClanCats\Container\Container;
use Closure;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\RenderTarget;

class QuickstartOptions
{
    /**
     * Application class
     * The class that is being constructed as the quickstart app instance. 
     * This NEEDS to be a subclass of QuickstartApp.
     */
    public string $appClass = QuickstartApp::class;

    /**
     * A prexisting instance of a service container, if none is given a new one will be created.
     */
    public ?Container $container = null;

    /**
     * The targeted amount of game update ticks per second of the game loop.
     */
    public float $gameLoopTickRate = 60.0;

    /**
     * The maximum amount of updates that can be executed, before 
     * a render call is forced.
     */
    public int $gameLoopMaxUpdatesPerFrame = 10;

    /**
     * The app windows title.
     */
    public string $windowTitle = 'VISU Quickstart';

    /**
     * The app windows width.
     */
    public int $windowWidth = 1280;

    /**
     * The app windows height.
     */
    public int $windowHeight = 720;

    /**
     * Should the app window have vsync enabled?
     */
    public bool $windowVsync = true;

    /**
     * Should the app window be headless? (Offscreen rendering)
     */
    public bool $windowHeadless = false;

    /**
     * Should the app automatically initalize and render a vector graphics frame in the draw call?
     */
    public bool $drawAutoRenderVectorGraphics = true;

    /**
     * A callable that is invoked once the app is ready to run.
     * 
     * Here you can prepare your game state, register services, callbacks etc.
     * 
     * @var null|Closure(QuickstartApp): void
     */
    public ?Closure $ready = null;

    /**
     * A callable in which the scene entities and components should initalized
     * 
     * This is called once after the `ready` stage as registered all binded systems.
     * So the general flow is as:
     *   - ready = bind systems
     *   - initializeScene = setup entities & components
     * 
     * @var null|Closure(QuickstartApp): void
     */
    public ?Closure $initializeScene = null;

    /**
     * A callable that is invoked to update the game state.
     * 
     * Note! It is not guranteed that this method is called every frame.
     * 
     * @var null|Closure(QuickstartApp): void
     */
    public ?Closure $update = null;

    /**
     * A callable that is called once per frame to configure the rendering pipeline
     * This is where you can attach render passes, use if you need higher / complex control over the rendering pipeline.
     * 
     * @var null|Closure(QuickstartApp, RenderContext, RenderTargetResource): void
     */
    public ?Closure $render = null;

    /**
     * Draw the scene. (You most definetly want to use this)
     * 
     * A callable that is called once per frame to draw your scene.
     * This call we be wrapped in a render pass of the pipeline build in `render`.
     * 
     * @var null|Closure(QuickstartApp, RenderContext, RenderTarget): void
     */
    public ?Closure $draw = null;
}