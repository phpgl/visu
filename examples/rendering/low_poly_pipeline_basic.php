<?php

use GL\Math\{GLM, Quat, Vec3};
use VISU\Component\VISULowPoly\LPDynamicModel;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Quickstart;
use VISU\Quickstart\{QuickstartApp, QuickstartOptions};
use VISU\System\VISUCameraSystem;
use VISU\System\VISULowPoly\{LPModelCollection, LPObjLoader, LPRenderingSystem};

$container = require __DIR__ . '/../bootstrap.php';

// Demo State
// --------------------------------------------------------------------
class LowPolyRendererDemoState 
{
    public VISUCameraSystem $cameraSystem;
    public LPRenderingSystem $renderingSystem;
    public LPModelCollection $models;

    // logo entity
    public int $logoEntity;

    // rotation state for interpolation
    public Quat $logoRotationPrevious;
    public Quat $logoRotationCurrent;
}

$state = new LowPolyRendererDemoState;

/**
 * Main Entry Point
 * 
 * ----------------------------------------------------------------------------
 */
$quickstart = new Quickstart(function(QuickstartOptions $app) use(&$state, $container)
{
    // Initalize the application
    // --------------------------------------------------------------------
    $app->container = $container;
    $app->ready = function(QuickstartApp $app) use(&$state) 
    {
        // create a model collection and load 
        $state->models = new LPModelCollection();
        $state->renderingSystem = new LPRenderingSystem($app->gl, $app->shaders, $state->models);

        // load the VISU models coming with the engine
        $loader = new LPObjLoader($app->gl);
        $loader->loadAllInDirectory(VISU_PATH_FRAMEWORK_RESOURCES . '/model/visu', $state->models);

        // to render 3D we need a camera
        $state->cameraSystem = new VISUCameraSystem($app->input, $app->dispatcher);

        // register the rendering system
        $app->bindSystems([
            $state->renderingSystem,
            $state->cameraSystem
        ]);
    };

    // Initalize the scene
    // --------------------------------------------------------------------
    $app->initializeScene = function(QuickstartApp $app) use(&$state) 
    {
        $state->cameraSystem->spawnDefaultFlyingCamera($app->entities, new Vec3(0.0, 0.0, 2.0));

        // spawn a visu model in the middle
        $state->logoEntity = $app->entities->create();
        $app->entities->attach($state->logoEntity, new LPDynamicModel('visu_logo'));
        $transform = $app->entities->attach($state->logoEntity, new Transform());
        $transform->orientation->rotate(GLM::radians(90.0), new Vec3(1.0, 0.0, 0.0));

        // initialize rotation state for interpolation
        $state->logoRotationPrevious = $transform->orientation->copy();
        $state->logoRotationCurrent = $transform->orientation->copy();
    };

    // Update the scene
    // --------------------------------------------------------------------
    $app->update = function(QuickstartApp $app) use(&$state) 
    {
        $app->updateSystem($state->cameraSystem);

        // store previous state before updating
        $state->logoRotationPrevious = $state->logoRotationCurrent->copy();

        // rotate the logo by a fixed amount per tick
        $state->logoRotationCurrent->rotate(GLM::radians(1.0), new Vec3(0.0, 0.0, 1.0));
    };

    // Render the scene
    // --------------------------------------------------------------------
    $app->render = function(QuickstartApp $app, RenderContext $context, RenderTargetResource $target) use(&$state) 
    {
        // interpolate between previous and current rotation state using compensation
        // this way you get butter smooth rotation
        $transform = $app->entities->get($state->logoEntity, Transform::class);
        $transform->orientation = Quat::slerp($state->logoRotationPrevious, $state->logoRotationCurrent, $context->compensation);
        $transform->markDirty();

        // make sure to tell the low poly rendering system which render target we are using
        $state->renderingSystem->setRenderTarget($target);

        $app->renderSystem($state->cameraSystem, $context);
        $app->renderSystem($state->renderingSystem, $context);

        // Example: You can override the ouput texture to any texture generated in the pipeline
        // the the code below we "debug" output the SSAO texture instead of the final render
        // $ssaoData = $context->data->get(SSAOData::class);
        // $quickstartPassData = $context->data->get(QuickstartPassData::class);
        // $quickstartPassData->outputTexture = $ssaoData->ssaoTexture;
    };
});

$quickstart->run();
