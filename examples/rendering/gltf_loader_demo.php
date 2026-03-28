<?php
/**
 * glTF Loader Demo
 *
 * Demonstrates loading a glTF/GLB model and rendering it with the PBR pipeline.
 * Pass a path to a .glb or .gltf file as the first argument.
 *
 * Usage:
 *   php examples/rendering/gltf_loader_demo.php path/to/model.glb
 */

use GL\Math\{Vec3, Vec4};
use VISU\Component\DirectionalLightComponent;
use VISU\Component\MeshRendererComponent;
use VISU\Component\PointLightComponent;
use VISU\Geo\Transform;
use VISU\Graphics\Loader\GltfLoader;
use VISU\Graphics\ModelCollection;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Quickstart;
use VISU\Quickstart\QuickstartApp;
use VISU\Quickstart\QuickstartOptions;
use VISU\System\Rendering3DSystem;
use VISU\System\VISUCameraSystem;

$container = require __DIR__ . '/../bootstrap.php';

// get model path from CLI args
$modelPath = $argv[1] ?? null;
if ($modelPath === null) {
    echo "Usage: php examples/rendering/gltf_loader_demo.php <path/to/model.glb>\n";
    echo "\nNo model specified. The demo will start with an empty scene.\n";
    echo "You can download test models from: https://github.com/KhronosGroup/glTF-Sample-Models\n\n";
}

class GltfDemoState
{
    public VISUCameraSystem $cameraSystem;
    public Rendering3DSystem $renderingSystem;
    public ModelCollection $models;
    public ?string $loadedModelName = null;
}

$state = new GltfDemoState;

$quickstart = new Quickstart(function (QuickstartOptions $app) use (&$state, $container, $modelPath) {
    $app->container = $container;
    $app->windowTitle = 'VISU — glTF Loader Demo';

    $app->ready = function (QuickstartApp $app) use (&$state, $modelPath) {
        $state->models = new ModelCollection();
        $state->renderingSystem = new Rendering3DSystem($app->gl, $app->shaders, $state->models);
        $state->cameraSystem = new VISUCameraSystem($app->input, $app->dispatcher);

        $app->bindSystems([
            $state->renderingSystem,
            $state->cameraSystem,
        ]);

        // load glTF model
        if ($modelPath !== null && file_exists($modelPath)) {
            $loader = new GltfLoader($app->gl);
            $model = $loader->load($modelPath);
            $state->models->add($model);
            $state->loadedModelName = $model->name;

            echo "Loaded model: {$model->name}\n";
            echo "Meshes: " . count($model->meshes) . "\n";
            echo "AABB: ({$model->aabb->min->x}, {$model->aabb->min->y}, {$model->aabb->min->z}) -> ({$model->aabb->max->x}, {$model->aabb->max->y}, {$model->aabb->max->z})\n";
        }
    };

    $app->initializeScene = function (QuickstartApp $app) use (&$state) {
        $state->cameraSystem->spawnDefaultFlyingCamera($app->entities, new Vec3(0.0, 2.0, 5.0));

        // sun
        $sun = $app->entities->getSingleton(DirectionalLightComponent::class);
        $sun->direction = new Vec3(0.5, 1.0, 0.3);
        $sun->direction->normalize();
        $sun->intensity = 2.0;

        // spawn model if loaded
        if ($state->loadedModelName !== null) {
            $entity = $app->entities->create();
            $app->entities->attach($entity, new MeshRendererComponent($state->loadedModelName));
            $app->entities->attach($entity, new Transform());
        }

        // add fill lights
        $fillLights = [
            [new Vec3(1, 0.95, 0.9), new Vec3(5, 4, 3)],
            [new Vec3(0.9, 0.95, 1), new Vec3(-5, 3, -2)],
        ];
        foreach ($fillLights as [$color, $pos]) {
            $light = $app->entities->create();
            $lightComp = new PointLightComponent($color, 3.0, 20.0);
            $lightComp->setAttenuationFromRange();
            $app->entities->attach($light, $lightComp);
            $transform = $app->entities->attach($light, new Transform());
            $transform->position = $pos;
            $transform->markDirty();
        }
    };

    $app->update = function (QuickstartApp $app) use (&$state) {
        $app->updateSystem($state->cameraSystem);
    };

    $app->render = function (QuickstartApp $app, RenderContext $context, RenderTargetResource $target) use (&$state) {
        $state->renderingSystem->setRenderTarget($target);
        $app->renderSystem($state->cameraSystem, $context);
        $app->renderSystem($state->renderingSystem, $context);
    };
});

$quickstart->run();
