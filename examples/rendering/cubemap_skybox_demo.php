<?php

/**
 * Cubemap/Skybox Rendering Example
 * 
 * This example demonstrates how to render a cubemap as a skybox using 
 * the CubemapRenderer and CubemapPass classes.
 * 
 * The example loads a cubemap from an HDRI file, or falls back to
 * a test cubemap with solid colors if the HDRI file is not found.
 * 
 * To use with a real HDRI file, place your .hdr file at:
 * examples/resources/assets/environment.hdr
 * 
 * Free HDRI files can be downloaded from:
 * - https://polyhaven.com/hdris
 * - https://hdrihaven.com/
 */

use VISU\Quickstart;
use VISU\Quickstart\QuickstartApp;
use VISU\Quickstart\QuickstartOptions;
use VISU\Graphics\Rendering\Renderer\CubemapRenderer;
use VISU\Graphics\Rendering\Resource\CubemapResource;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\RenderTarget;
use VISU\Graphics\Cubemap;
use VISU\Graphics\HDRIToCubemap;
use VISU\Graphics\TextureOptions;
use VISU\System\VISUCameraSystem;
use GL\Buffer\UByteBuffer;
use GL\Math\{GLM, Vec3, Mat4};

if (!defined('DS')) { define('DS', DIRECTORY_SEPARATOR); }
set_time_limit(0);

require __DIR__ . '/../bootstrap.php';

class CubemapDemoApp extends QuickstartApp {
    public Cubemap $cubemap;
    public CubemapRenderer $cubemapRenderer;
    public VISUCameraSystem $cameraSystem;

    public function setupDrawAfter(RenderContext $context, RenderTargetResource $renderTarget): void
    {
        // get camera data from the context (set by camera system during render)
        $cameraData = $context->data->get(CameraData::class);
        
        // import cubemap into the render pipeline
        $cubemapRes = $context->pipeline->importCubemap('skybox_cubemap', $this->cubemap);
        
        // add the skybox pass (pass reads camera data internally)
        $this->cubemapRenderer->attachSkyboxPass(
            $context->pipeline,
            $renderTarget, 
            $cubemapRes,
        );
    }
}

$quickstart = new Quickstart(function(QuickstartOptions $app)
{
    $app->appClass = CubemapDemoApp::class;
    
    $app->ready = function(QuickstartApp $app) {
        /** @var CubemapDemoApp $app */
        
        // define path to HDRI file - change this to point to your HDRI file
        $hdriPath = '/Users/mariodoring/Downloads/cedar_bridge_sunset_1_4k.hdr';
        
        // create HDRI to cubemap converter
        $converter = new HDRIToCubemap($app->gl);
        
        // convert HDRI to cubemap (512x512 faces)
        $app->cubemap = $converter->convert($hdriPath, 1024);
        
        // create the cubemap renderer
        $app->cubemapRenderer = new CubemapRenderer($app->gl);
        
        // create camera system for 3D navigation
        $app->cameraSystem = new VISUCameraSystem($app->input, $app->dispatcher);
        
        // register the camera system
        $app->bindSystems([$app->cameraSystem]);
    };
    
    $app->initializeScene = function(QuickstartApp $app) {
        /** @var CubemapDemoApp $app */
        
        // spawn a flying camera at origin
        $app->cameraSystem->spawnDefaultFlyingCamera($app->entities, new Vec3(0.0, 0.0, 0.0));
    };
    
    $app->update = function(QuickstartApp $app) {
        /** @var CubemapDemoApp $app */
        
        // update the camera system
        $app->updateSystem($app->cameraSystem);
    };
    
    $app->render = function(QuickstartApp $app, RenderContext $context, RenderTargetResource $target) {
        /** @var CubemapDemoApp $app */
        
        // render the camera system (this sets up CameraData in the context)
        $app->renderSystem($app->cameraSystem, $context);
    };
    
});

$quickstart->run();