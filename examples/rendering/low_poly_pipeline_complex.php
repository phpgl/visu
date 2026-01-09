<?php

use GL\Math\GLM;
use GL\Math\Vec3;
use VISU\Component\VISULowPoly\LPDynamicModel;
use VISU\Component\VISULowPoly\LPStaticModel;
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
        // $app->loadCompatGPUProfiler();
        
        // create a model collection and load 
        $state->models = new LPModelCollection();
        $state->renderingSystem = new LPRenderingSystem($app->gl, $app->shaders, $state->models);

        // load the VISU models coming with the engine
        $loader = new LPObjLoader($app->gl);
        $loader->loadAllInDirectory(VISU_PATH_RESOURCES . '/assets/models/lp/', $state->models);

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

        // we spawn 5000 random low poly models in the scene
        $availableModels = $state->models->models;

        for ($i = 0; $i < 50000; $i++) {
            $modelIndex = rand(0, count($availableModels) - 1);
            $modelName = array_keys($availableModels)[$modelIndex];

            $entity = $app->entities->create();
            $transform = $app->entities->attach($entity, new Transform());

            // random position
            $transform->position = new Vec3(
                rand(-1000, 1000) / 10.0,
                rand(-1000, 1000) / 10.0,
                rand(-1000, 1000) / 10.0
            );

            // random rotation
            $transform->orientation->rotate(GLM::radians(rand(0, 360)), new Vec3(0.0, 1.0, 0.0));
            $transform->orientation->rotate(GLM::radians(rand(0, 360)), new Vec3(1.0, 0.0, 0.0));

            // random scale
            $scale = rand(5, 20) / 10.0;
            $transform->scale = new Vec3($scale, $scale, $scale);

            // attach the low poly model component
            $app->entities->attach($entity, new LPStaticModel($modelName));
        }
    };

    $app->update = function(QuickstartApp $app) use(&$state) 
    {
        $app->updateSystem($state->cameraSystem);
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
