<?php

use GL\Math\GLM;
use GL\Math\Vec3;
use VISU\Component\VISULowPoly\DynamicRenderableModel;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\Pass\GBufferPassData;
use VISU\Graphics\Rendering\Pass\SSAOData;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\RenderTarget;
use VISU\Quickstart;
use VISU\Quickstart\QuickstartApp;
use VISU\Quickstart\QuickstartOptions;
use VISU\Quickstart\Render\QuickstartPassData;
use VISU\System\VISUCameraSystem;
use VISU\System\VISULowPoly\LPModelCollection;
use VISU\System\VISULowPoly\LPObjLoader;
use VISU\System\VISULowPoly\LPRenderingSystem;

$container = require __DIR__ . '/../bootstrap.php';

class LowPolyRendererDemoState 
{
    public VISUCameraSystem $cameraSystem;
    public LPRenderingSystem $renderingSystem;
    public LPModelCollection $models;
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
        $logoEntity = $app->entities->create();
        $app->entities->attach($logoEntity, new DynamicRenderableModel('visu_logo.obj'));
        $transform = $app->entities->attach($logoEntity, new Transform());
        $transform->orientation->rotate(GLM::radians(90.0), new Vec3(1.0, 0.0, 0.0));
    };

    $app->update = function(QuickstartApp $app) use(&$state) 
    {
        $app->updateSystem($state->cameraSystem);

        // rotate the logo
        $model = $app->entities->firstWith(DynamicRenderableModel::class);
        $transform = $app->entities->get($model, Transform::class);
        $transform->orientation->rotate(GLM::radians(1.0), new Vec3(0.0, 0.0, 1.0));
        $transform->markDirty();
    };

    $app->render = function(QuickstartApp $app, RenderContext $context, RenderTargetResource $target) use(&$state) 
    {
        // make sure to tell the low poly rendering system which render target we are using
        $state->renderingSystem->setRenderTarget($target);

        $app->renderSystem($state->cameraSystem, $context);
        $app->renderSystem($state->renderingSystem, $context);

        // $ssaoData = $context->data->get(SSAOData::class);
        // $quickstartPassData = $context->data->get(QuickstartPassData::class);

        // $quickstartPassData->outputTexture = $ssaoData->ssaoTexture;
    };
});

$quickstart->run();
