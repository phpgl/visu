<?php

/**
 * Low Poly Rendering Pipeline with Cubemap Environment Example
 * 
 * This example demonstrates how to use the LPRenderingSystem with cubemap
 * environment lighting and skybox rendering. It shows how to load an HDRI
 * environment map and use it for both skybox rendering and environment lighting.
 * 
 * NOISE REDUCTION IMPROVEMENTS:
 * - Increased cubemap resolution to 1024x1024 for higher quality source data
 * - Increased sample count from 1024 to 4096 in prefilter shader
 * - Improved mip level calculation with better clamping
 * - Enabled mipmapping and anisotropic filtering on converted cubemaps
 * - Increased default prefilter resolution from 128 to 256
 * 
 * Features demonstrated:
 * - Loading and converting HDRI to cubemap
 * - Setting environment cubemap in LPRenderingSystem
 * - Environment-based ambient lighting and reflections with reduced noise
 * - Optional skybox rendering
 * - Procedural sphere generation with PBR material grid
 * 
 * Free HDRI files can be downloaded from:
 * - https://polyhaven.com/hdris
 * - https://hdrihaven.com/
 * 
 * PERFORMANCE NOTE: Higher quality settings may reduce performance on lower-end GPUs.
 * If you experience performance issues, consider:
 * - Reducing cubemap size back to 512
 * - Using lower resolution HDRI files
 * - Reducing the sample count in the prefilter shader
 */

use GL\Buffer\FloatBuffer;
use GL\Math\{GLM, Quat, Vec3};
use VISU\Component\VISULowPoly\LPDynamicModel;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Cubemap;
use VISU\Graphics\HDRIToCubemap;
use VISU\Quickstart;
use VISU\Quickstart\{QuickstartApp, QuickstartOptions};
use VISU\System\VISUCameraSystem;
use VISU\System\VISULowPoly\{LPMaterial, LPModel, LPModelCollection, LPObjLoader, LPRenderingSystem, LPVertexBuffer};

$container = require __DIR__ . '/../bootstrap.php';

/**
 * Generates a UV sphere mesh with position and normal data
 * 
 * @param float $radius The radius of the sphere
 * @param int $segments Number of horizontal segments (longitude)
 * @param int $rings Number of vertical rings (latitude)
 * @return FloatBuffer Buffer containing [pos.x, pos.y, pos.z, norm.x, norm.y, norm.z] per vertex
 */
function generateSphereMesh(float $radius = 1.0, int $segments = 32, int $rings = 16): FloatBuffer
{
    $buffer = new FloatBuffer();
    
    // generate triangles directly with position + normal only (6 floats per vertex)
    // this matches the format expected by LPObjLoader::importMesh
    for ($ring = 0; $ring < $rings; $ring++) {
        for ($seg = 0; $seg < $segments; $seg++) {
            // calculate the four corners of this quad
            $phi0 = M_PI * $ring / $rings;
            $phi1 = M_PI * ($ring + 1) / $rings;
            
            $theta0 = 2.0 * M_PI * $seg / $segments;
            $theta1 = 2.0 * M_PI * ($seg + 1) / $segments;
            
            // calculate positions and normals for the quad corners
            $v00 = generateSphereVertex($phi0, $theta0, $radius);
            $v01 = generateSphereVertex($phi0, $theta1, $radius);
            $v10 = generateSphereVertex($phi1, $theta0, $radius);
            $v11 = generateSphereVertex($phi1, $theta1, $radius);
            
            // first triangle (v00, v01, v10) - counter-clockwise winding for outward normals
            addVertexToBuffer($buffer, $v00);
            addVertexToBuffer($buffer, $v01);
            addVertexToBuffer($buffer, $v10);
            
            // second triangle (v01, v11, v10) - counter-clockwise winding for outward normals
            addVertexToBuffer($buffer, $v01);
            addVertexToBuffer($buffer, $v11);
            addVertexToBuffer($buffer, $v10);
        }
    }
    
    return $buffer;
}

function generateSphereVertex(float $phi, float $theta, float $radius): array
{
    $y = cos($phi);
    $ringRadius = sin($phi);
    $x = $ringRadius * cos($theta);
    $z = $ringRadius * sin($theta);
    
    return [
        'pos' => [$x * $radius, $y * $radius, $z * $radius],
        'norm' => [$x, $y, $z]  // normal is normalized position for unit sphere
    ];
}

function addVertexToBuffer(FloatBuffer $buffer, array $vertex): void
{
    // position + normal format (6 floats per vertex)
    // this matches what LPObjLoader::importMesh expects
    $buffer->push($vertex['pos'][0]);
    $buffer->push($vertex['pos'][1]);
    $buffer->push($vertex['pos'][2]);
    
    $buffer->push($vertex['norm'][0]);
    $buffer->push($vertex['norm'][1]);
    $buffer->push($vertex['norm'][2]);
}

// Demo State
// --------------------------------------------------------------------
class LowPolyWithCubemapDemoState 
{
    public VISUCameraSystem $cameraSystem;
    public LPRenderingSystem $renderingSystem;
    public LPModelCollection $models;
    public ?Cubemap $environmentCubemap = null;

    // logo entity
    public int $logoEntity;

    // rotation state for interpolation
    public Quat $logoRotationPrevious;
    public Quat $logoRotationCurrent;

    // grid configuration
    public const GRID_ROUGHNESS_STEPS = 10;  // x-axis: roughness 0.0 to 1.0
    public const GRID_METALLIC_STEPS = 5;   // y-axis: metallic 0.0 or 1.0
    public const SPHERE_SPACING = 1.2;

    // sphere entities
    /** @var array<int> */
    public array $sphereEntities = [];
}

$state = new LowPolyWithCubemapDemoState;

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
        // create a model collection
        $state->models = new LPModelCollection();
        $state->renderingSystem = new LPRenderingSystem($app->gl, $app->shaders, $state->models);

        // load the VISU models coming with the engine (including logo)
        $loader = new LPObjLoader($app->gl);
        $loader->loadAllInDirectory(VISU_PATH_FRAMEWORK_RESOURCES . '/model/visu', $state->models);

        // create a vertex buffer for our procedural spheres
        $vb = new LPVertexBuffer($app->gl);

        // generate sphere mesh data once (position + normal format)
        $sphereMeshData = generateSphereMesh(0.5, 32, 16);

        // create sphere models with varying roughness and metallic values
        // x-axis: roughness (0.0 to 1.0)
        // y-axis: metallic (0.0 = dielectric, 1.0 = metallic)
        $baseColor = new Vec3(0.8, 0.2, 0.2); // red-ish base color
        $baseColor = new Vec3(1.);

        for ($my = 0; $my < LowPolyWithCubemapDemoState::GRID_METALLIC_STEPS; $my++) {
            $metallic = $my / max(1, LowPolyWithCubemapDemoState::GRID_METALLIC_STEPS - 1);
            
            for ($rx = 0; $rx < LowPolyWithCubemapDemoState::GRID_ROUGHNESS_STEPS; $rx++) {
                $roughness = $rx / max(1, LowPolyWithCubemapDemoState::GRID_ROUGHNESS_STEPS - 1);

                $roughness = min(max($roughness, 0.04), 1.0); // avoid 0.0 roughness for better visibility
                $metallic = min(max($metallic, 0.0), 1.0);

                // create unique material for this sphere
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
        $hdriPath = '/Users/mariodoring/Downloads/cedar_bridge_sunset_1_4k.hdr';
        // $hdriPath = '/Users/mariodoring/Downloads/industrial_wooden_attic_4k.hdr';
        // $hdriPath = '/Users/mariodoring/Downloads/newport_loft.hdr';
        
        $cubemapResolution = 1024; 

        if (file_exists($hdriPath)) {
            // convert HDRI to cubemap (1024x1024 faces for higher quality)
            $converter = new HDRIToCubemap($app->gl);
            $state->environmentCubemap = $converter->convert($hdriPath, $cubemapResolution);
            echo "Loaded HDRI environment: $hdriPath\n";
            echo "Cubemap resolution: {$cubemapResolution}x{$cubemapResolution} per face\n";
        } else {
            echo "No HDRI file found at: $hdriPath\n";
            echo "You can download free HDRI files from https://polyhaven.com/hdris\n";
            echo "Place your .hdr file at: /Users/mariodoring/Downloads/cedar_bridge_sunset_1_4k.hdr\n";
        }

        // set the environment cubemap in the rendering system
        if ($state->environmentCubemap) {
            $state->renderingSystem->setEnvironmentCubemap($state->environmentCubemap);
            $state->renderingSystem->renderSkybox = true; // enable skybox rendering
        }

        // to render 3D we need a camera
        $state->cameraSystem = new VISUCameraSystem($app->input, $app->dispatcher);

        // register the rendering system
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
        $gridWidth = LowPolyWithCubemapDemoState::GRID_ROUGHNESS_STEPS * LowPolyWithCubemapDemoState::SPHERE_SPACING;
        $gridHeight = LowPolyWithCubemapDemoState::GRID_METALLIC_STEPS * LowPolyWithCubemapDemoState::SPHERE_SPACING;
        $cameraDistance = max($gridWidth, $gridHeight) * 1.2;
        
        $state->cameraSystem->spawnDefaultFlyingCamera($app->entities, new Vec3(
            $gridWidth * 0.5 - LowPolyWithCubemapDemoState::SPHERE_SPACING * 0.5,
            $gridHeight * 0.5 - LowPolyWithCubemapDemoState::SPHERE_SPACING * 0.5,
            $cameraDistance
        ));

        // spawn a visu logo in the middle
        $state->logoEntity = $app->entities->create();
        $app->entities->attach($state->logoEntity, new LPDynamicModel('visu_logo'));
        $logoTransform = $app->entities->attach($state->logoEntity, new Transform());
        $logoTransform->orientation->rotate(GLM::radians(90.0), new Vec3(1.0, 0.0, 0.0));
        $logoTransform->position = new Vec3(
            $gridWidth * 0.5 - LowPolyWithCubemapDemoState::SPHERE_SPACING * 0.5,
            $gridHeight * 0.5 - LowPolyWithCubemapDemoState::SPHERE_SPACING * 0.5,
            -1.0  // place logo slightly in front of the sphere grid
        );

        // initialize rotation state for interpolation
        $state->logoRotationPrevious = $logoTransform->orientation->copy();
        $state->logoRotationCurrent = $logoTransform->orientation->copy();

        // spawn sphere grid
        // x-axis: roughness (left = 0.0, right = 1.0)
        // y-axis: metallic (bottom = 0.0, top = 1.0)
        for ($my = 0; $my < LowPolyWithCubemapDemoState::GRID_METALLIC_STEPS; $my++) {
            for ($rx = 0; $rx < LowPolyWithCubemapDemoState::GRID_ROUGHNESS_STEPS; $rx++) {
                $entity = $app->entities->create();
                
                // attach the corresponding sphere model
                $modelName = "sphere_{$rx}_{$my}";
                $app->entities->attach($entity, new LPDynamicModel($modelName));
                
                // position in grid
                $transform = $app->entities->attach($entity, new Transform());
                $transform->position = new Vec3(
                    $rx * LowPolyWithCubemapDemoState::SPHERE_SPACING,
                    $my * LowPolyWithCubemapDemoState::SPHERE_SPACING,
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
        $logoTransform = $app->entities->get($state->logoEntity, Transform::class);
        $logoTransform->orientation = Quat::slerp($state->logoRotationPrevious, $state->logoRotationCurrent, $context->compensation);
        $logoTransform->markDirty();

        // make sure to tell the low poly rendering system which render target we are using
        $state->renderingSystem->setRenderTarget($target);

        $app->renderSystem($state->cameraSystem, $context);
        $app->renderSystem($state->renderingSystem, $context);
    };
});

echo "PBR Material Debug Grid with Enhanced IBL\n";
echo "==========================================\n";
echo "Controls:\n";
echo "  WASD/Mouse - Fly around\n";
echo "\n";
echo "IBL Quality Improvements:\n";
echo "  - 4096 samples for prefiltered environment maps\n";
echo "  - 1024x1024 cubemap resolution (configurable)\n";
echo "  - 256x256 prefilter maps with better mip chaining\n";
echo "  - Improved filtering and mip level calculations\n";
echo "  - Anisotropic filtering when supported\n";
echo "\n";
echo "Noise should be significantly reduced compared to default settings.\n";
echo "Adjust cubemapResolution variable for performance vs quality trade-off.\n";
echo "\n";

$quickstart->run();