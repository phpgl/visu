<?php

/**
 * PBR Materials Example
 *
 * This example renders a grid of spheres to showcase physically based
 * rendering. Each sphere uses a unique material so you can read the full
 * material response at a glance:
 * - x-axis: roughness (0.0 left -> 1.0 right)
 * - y-axis: metallic  (0.0 bottom -> 1.0 top)
 *
 * The grid is lit by image based lighting (IBL) derived from an HDRI
 * environment map, which is also rendered as an optional skybox. If no HDRI
 * is found the grid still renders, just without environment lighting.
 *
 * Free HDRI files can be downloaded from:
 * - https://polyhaven.com/hdris
 * - https://hdrihaven.com/
 */

use GL\Buffer\FloatBuffer;
use GL\Math\Vec3;
use VISU\Component\VISULowPoly\LPDynamicModel;
use VISU\Geo\Primitive\Sphere;
use VISU\Geo\Primitive\VertexLayout;
use VISU\Geo\Transform;
use VISU\Graphics\Cubemap;
use VISU\Graphics\HDRIToCubemap;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Quickstart;
use VISU\Quickstart\QuickstartApp;
use VISU\Quickstart\QuickstartOptions;
use VISU\System\VISUCameraSystem;
use VISU\System\VISULowPoly\LPMaterial;
use VISU\System\VISULowPoly\LPModel;
use VISU\System\VISULowPoly\LPModelCollection;
use VISU\System\VISULowPoly\LPObjLoader;
use VISU\System\VISULowPoly\LPRenderingSystem;
use VISU\System\VISULowPoly\LPVertexBuffer;

$container = require __DIR__ . '/../bootstrap.php';

// Demo State
// --------------------------------------------------------------------
class PBRMaterialsDemoState
{
    public VISUCameraSystem $cameraSystem;
    public LPRenderingSystem $renderingSystem;
    public LPModelCollection $models;
    public ?Cubemap $environmentCubemap = null;

    // grid configuration
    public const GRID_ROUGHNESS_STEPS = 10;  // x-axis: roughness 0.0 to 1.0
    public const GRID_METALLIC_STEPS = 5;    // y-axis: metallic 0.0 to 1.0
    public const SPHERE_SPACING = 1.2;

    // sphere entities
    /** @var array<int> */
    public array $sphereEntities = [];
}

$state = new PBRMaterialsDemoState;

/**
 * Main Entry Point
 *
 * ----------------------------------------------------------------------------
 */
$quickstart = new Quickstart(function(QuickstartOptions $app) use(&$state, $container)
{
    // Initialize the application
    // --------------------------------------------------------------------
    $app->container = $container;
    $app->ready = function(QuickstartApp $app) use(&$state)
    {
        // create a model collection and the rendering system
        $state->models = new LPModelCollection();
        $state->renderingSystem = new LPRenderingSystem($app->gl, $app->shaders, $state->models);

        // create a vertex buffer for our procedural spheres
        $vb = new LPVertexBuffer($app->gl);
        $loader = new LPObjLoader($app->gl);

        // generate sphere mesh data once (position + normal format, which is what
        // LPObjLoader::importMesh consumes)
        $sphereMeshData = new FloatBuffer();
        Sphere::make(
            VertexLayout::with([VertexLayout::position, VertexLayout::normal]),
            $sphereMeshData,
            0.5, 32, 16
        );

        // create sphere models with varying roughness and metallic values
        // x-axis: roughness (0.0 to 1.0)
        // y-axis: metallic (0.0 = dielectric, 1.0 = metallic)
        $baseColor = new Vec3(1.0);

        for ($my = 0; $my < PBRMaterialsDemoState::GRID_METALLIC_STEPS; $my++) {
            $metallic = $my / max(1, PBRMaterialsDemoState::GRID_METALLIC_STEPS - 1);

            for ($rx = 0; $rx < PBRMaterialsDemoState::GRID_ROUGHNESS_STEPS; $rx++) {
                $roughness = $rx / max(1, PBRMaterialsDemoState::GRID_ROUGHNESS_STEPS - 1);

                $roughness = min(max($roughness, 0.04), 1.0); // avoid 0.0 roughness for better visibility
                $metallic = min(max($metallic, 0.0), 1.0);

                // create a unique material for this sphere
                $materialName = sprintf("pbr_r%.2f_m%.2f", $roughness, $metallic);
                $material = new LPMaterial(
                    $materialName,
                    $baseColor->copy(),
                    $roughness,
                    $metallic
                );

                // import mesh with this material
                $mesh = $loader->importMesh($sphereMeshData, $material, $vb);

                // create model and add to collection
                $model = new LPModel("sphere_{$rx}_{$my}", [$mesh]);
                $model->recalculateAABB();
                $state->models->add($model);
            }
        }

        // upload all vertex data to GPU
        $vb->upload();

        // create environment cubemap from HDRI (if available)
        $hdriPath = VISU_PATH_RESOURCES . '/assets/hdri/cowboy_town_saloon_2k.hdr';
        $cubemapResolution = 1024;

        if (file_exists($hdriPath)) {
            // convert HDRI to cubemap for image based lighting
            $converter = new HDRIToCubemap($app->gl);
            $state->environmentCubemap = $converter->convert($hdriPath, $cubemapResolution);
            echo "Loaded HDRI environment: $hdriPath\n";
        } else {
            echo "No HDRI file found at: $hdriPath\n";
            echo "Download free HDRI files from https://polyhaven.com/hdris and place one there.\n";
        }

        // feed the environment cubemap into the rendering system for IBL + skybox
        if ($state->environmentCubemap) {
            $state->renderingSystem->setEnvironmentCubemap($state->environmentCubemap);
            $state->renderingSystem->renderSkybox = true;
        }

        // to render 3D we need a camera
        $state->cameraSystem = new VISUCameraSystem($app->input, $app->dispatcher);

        // register the systems
        $app->bindSystems([
            $state->renderingSystem,
            $state->cameraSystem
        ]);
    };

    // Initialize the scene
    // --------------------------------------------------------------------
    $app->initializeScene = function(QuickstartApp $app) use(&$state)
    {
        // position camera to see the entire grid
        $gridWidth = PBRMaterialsDemoState::GRID_ROUGHNESS_STEPS * PBRMaterialsDemoState::SPHERE_SPACING;
        $gridHeight = PBRMaterialsDemoState::GRID_METALLIC_STEPS * PBRMaterialsDemoState::SPHERE_SPACING;
        $cameraDistance = max($gridWidth, $gridHeight) * 1.2;

        $state->cameraSystem->spawnDefaultFlyingCamera($app->entities, new Vec3(
            $gridWidth * 0.5 - PBRMaterialsDemoState::SPHERE_SPACING * 0.5,
            $gridHeight * 0.5 - PBRMaterialsDemoState::SPHERE_SPACING * 0.5,
            $cameraDistance
        ));

        // spawn sphere grid
        // x-axis: roughness (left = 0.0, right = 1.0)
        // y-axis: metallic (bottom = 0.0, top = 1.0)
        for ($my = 0; $my < PBRMaterialsDemoState::GRID_METALLIC_STEPS; $my++) {
            for ($rx = 0; $rx < PBRMaterialsDemoState::GRID_ROUGHNESS_STEPS; $rx++) {
                $entity = $app->entities->create();

                // attach the corresponding sphere model
                $modelName = "sphere_{$rx}_{$my}";
                $app->entities->attach($entity, new LPDynamicModel($modelName));

                // position in grid
                $transform = $app->entities->attach($entity, new Transform());
                $transform->position = new Vec3(
                    $rx * PBRMaterialsDemoState::SPHERE_SPACING,
                    $my * PBRMaterialsDemoState::SPHERE_SPACING,
                    0.0
                );

                $state->sphereEntities[] = $entity;
            }
        }

        echo "Spawned " . count($state->sphereEntities) . " spheres in a grid\n";
        echo "X-axis: Roughness (0.0 left -> 1.0 right)\n";
        echo "Y-axis: Metallic (0.0 bottom -> 1.0 top)\n";
    };

    // Update the scene
    // --------------------------------------------------------------------
    $app->update = function(QuickstartApp $app) use(&$state)
    {
        $app->updateSystem($state->cameraSystem);
    };

    // Render the scene
    // --------------------------------------------------------------------
    $app->render = function(QuickstartApp $app, RenderContext $context, RenderTargetResource $target) use(&$state)
    {
        // tell the rendering system which render target we are using
        $state->renderingSystem->setRenderTarget($target);

        $app->renderSystem($state->cameraSystem, $context);
        $app->renderSystem($state->renderingSystem, $context);
    };
});

echo "PBR Materials\n";
echo "=============\n";
echo "Controls:\n";
echo "  WASD/Mouse - Fly around\n";
echo "\n";

$quickstart->run();
