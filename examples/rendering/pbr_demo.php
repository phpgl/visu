<?php
/**
 * PBR Rendering Demo
 *
 * Demonstrates the PBR material system with the new Rendering3DSystem.
 * Renders a grid of spheres with varying metallic and roughness values
 * using procedurally generated geometry.
 */

use GL\Buffer\FloatBuffer;
use GL\Math\{GLM, Vec3, Vec4};
use VISU\Component\DirectionalLightComponent;
use VISU\Component\MeshRendererComponent;
use VISU\Component\PointLightComponent;
use VISU\Geo\AABB;
use VISU\Geo\Transform;
use VISU\Graphics\Material;
use VISU\Graphics\Mesh3D;
use VISU\Graphics\Model3D;
use VISU\Graphics\ModelCollection;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Quickstart;
use VISU\Quickstart\QuickstartApp;
use VISU\Quickstart\QuickstartOptions;
use VISU\System\Rendering3DSystem;
use VISU\System\VISUCameraSystem;

$container = require __DIR__ . '/../bootstrap.php';

class PBRDemoState
{
    public VISUCameraSystem $cameraSystem;
    public Rendering3DSystem $renderingSystem;
    public ModelCollection $models;
}

$state = new PBRDemoState;

/**
 * Generate a UV sphere mesh (interleaved: pos3 + normal3 + uv2 + tangent4)
 */
function generateSphere(int $segments = 32, int $rings = 16, float $radius = 1.0): FloatBuffer
{
    $buffer = new FloatBuffer();

    for ($j = 0; $j < $rings; $j++) {
        for ($i = 0; $i < $segments; $i++) {
            // two triangles per quad
            $indices = [
                [$j, $i], [$j + 1, $i], [$j + 1, $i + 1],
                [$j, $i], [$j + 1, $i + 1], [$j, $i + 1],
            ];

            foreach ($indices as [$rr, $ss]) {
                $theta = $rr * M_PI / $rings;
                $phi = $ss * 2.0 * M_PI / $segments;

                $x = sin($theta) * cos($phi);
                $y = cos($theta);
                $z = sin($theta) * sin($phi);

                $u = $ss / $segments;
                $v = $rr / $rings;

                // tangent (along phi direction)
                $tx = -sin($phi);
                $ty = 0.0;
                $tz = cos($phi);

                // position
                $buffer->push($x * $radius);
                $buffer->push($y * $radius);
                $buffer->push($z * $radius);
                // normal
                $buffer->push($x);
                $buffer->push($y);
                $buffer->push($z);
                // uv
                $buffer->push($u);
                $buffer->push($v);
                // tangent (vec4, w=1 for handedness)
                $buffer->push($tx);
                $buffer->push($ty);
                $buffer->push($tz);
                $buffer->push(1.0);
            }
        }
    }

    return $buffer;
}

/**
 * Generate a flat plane mesh
 */
function generatePlane(float $size = 10.0): FloatBuffer
{
    $buffer = new FloatBuffer();
    $h = $size * 0.5;

    $vertices = [
        [-$h, 0, -$h, 0, 1, 0, 0, 0, 1, 0, 0, 1],
        [ $h, 0, -$h, 0, 1, 0, 1, 0, 1, 0, 0, 1],
        [ $h, 0,  $h, 0, 1, 0, 1, 1, 1, 0, 0, 1],
        [-$h, 0, -$h, 0, 1, 0, 0, 0, 1, 0, 0, 1],
        [ $h, 0,  $h, 0, 1, 0, 1, 1, 1, 0, 0, 1],
        [-$h, 0,  $h, 0, 1, 0, 0, 1, 1, 0, 0, 1],
    ];

    foreach ($vertices as $v) {
        foreach ($v as $f) $buffer->push($f);
    }

    return $buffer;
}

$quickstart = new Quickstart(function (QuickstartOptions $app) use (&$state, $container) {
    $app->container = $container;
    $app->windowTitle = 'VISU — PBR Material Demo';

    $app->ready = function (QuickstartApp $app) use (&$state) {
        $state->models = new ModelCollection();
        $state->renderingSystem = new Rendering3DSystem($app->gl, $app->shaders, $state->models);
        $state->cameraSystem = new VISUCameraSystem($app->input, $app->dispatcher);

        $app->bindSystems([
            $state->renderingSystem,
            $state->cameraSystem,
        ]);

        // create sphere models with varying PBR properties
        $rows = 7;    // metallic steps
        $cols = 7;    // roughness steps

        for ($m = 0; $m < $rows; $m++) {
            for ($r = 0; $r < $cols; $r++) {
                $metallic = $m / ($rows - 1);
                $roughness = max(0.05, $r / ($cols - 1)); // clamp min roughness

                $matName = "sphere_m{$m}_r{$r}";
                $material = new Material(
                    name: $matName,
                    albedoColor: new Vec4(0.9, 0.2, 0.2, 1.0),
                    metallic: $metallic,
                    roughness: $roughness,
                );

                $sphereData = generateSphere(24, 12, 0.4);
                $mesh = new Mesh3D($app->gl, $material, new AABB(
                    new Vec3(-0.4, -0.4, -0.4),
                    new Vec3(0.4, 0.4, 0.4),
                ));
                $mesh->uploadVertices($sphereData);

                $model = new Model3D($matName);
                $model->addMesh($mesh);
                $model->recalculateAABB();
                $state->models->add($model);
            }
        }

        // floor plane
        $floorMat = new Material(
            name: 'floor',
            albedoColor: new Vec4(0.3, 0.3, 0.35, 1.0),
            metallic: 0.0,
            roughness: 0.8,
        );
        $planeMesh = new Mesh3D($app->gl, $floorMat, new AABB(
            new Vec3(-10, -0.01, -10), new Vec3(10, 0.01, 10)
        ));
        $planeMesh->uploadVertices(generatePlane(20.0));
        $floorModel = new Model3D('floor');
        $floorModel->addMesh($planeMesh);
        $floorModel->recalculateAABB();
        $state->models->add($floorModel);
    };

    $app->initializeScene = function (QuickstartApp $app) use (&$state) {
        $state->cameraSystem->spawnDefaultFlyingCamera($app->entities, new Vec3(3.5, 4.0, 10.0));

        // configure sun
        $sun = $app->entities->getSingleton(DirectionalLightComponent::class);
        $sun->direction = new Vec3(0.5, 1.0, 0.3);
        $sun->direction->normalize();
        $sun->intensity = 2.0;

        // spawn sphere grid
        $rows = 7;
        $cols = 7;
        $spacing = 1.1;
        $startX = -($cols - 1) * $spacing * 0.5;
        $startZ = -($rows - 1) * $spacing * 0.5;

        for ($m = 0; $m < $rows; $m++) {
            for ($r = 0; $r < $cols; $r++) {
                $entity = $app->entities->create();
                $app->entities->attach($entity, new MeshRendererComponent("sphere_m{$m}_r{$r}"));
                $transform = $app->entities->attach($entity, new Transform());
                $transform->position->x = $startX + $r * $spacing;
                $transform->position->y = 1.5;
                $transform->position->z = $startZ + $m * $spacing;
                $transform->markDirty();
            }
        }

        // floor
        $floor = $app->entities->create();
        $app->entities->attach($floor, new MeshRendererComponent('floor'));
        $app->entities->attach($floor, new Transform());

        // point lights
        $lightColors = [
            new Vec3(1.0, 0.8, 0.6),  // warm
            new Vec3(0.6, 0.8, 1.0),  // cool
            new Vec3(0.2, 1.0, 0.4),  // green
            new Vec3(1.0, 0.3, 0.7),  // pink
        ];
        $lightPositions = [
            new Vec3(-4.0, 3.0, 4.0),
            new Vec3(4.0, 3.0, -4.0),
            new Vec3(-4.0, 3.0, -4.0),
            new Vec3(4.0, 3.0, 4.0),
        ];

        for ($i = 0; $i < 4; $i++) {
            $light = $app->entities->create();
            $lightComp = new PointLightComponent($lightColors[$i], 5.0, 15.0);
            $lightComp->setAttenuationFromRange();
            $app->entities->attach($light, $lightComp);
            $transform = $app->entities->attach($light, new Transform());
            $transform->position = $lightPositions[$i];
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
