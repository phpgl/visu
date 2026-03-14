<?php
/**
 * Multi-Light Demo
 *
 * Demonstrates the PBR deferred lighting pipeline with multiple point lights
 * orbiting around a scene of objects with different PBR materials.
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

class MultiLightDemoState
{
    public VISUCameraSystem $cameraSystem;
    public Rendering3DSystem $renderingSystem;
    public ModelCollection $models;
    /** @var array<int> point light entity IDs */
    public array $lightEntities = [];
}

$state = new MultiLightDemoState;

/**
 * Generate a UV sphere
 */
function genSphere(int $segments = 32, int $rings = 16, float $radius = 1.0): FloatBuffer
{
    $buffer = new FloatBuffer();
    for ($j = 0; $j < $rings; $j++) {
        for ($i = 0; $i < $segments; $i++) {
            foreach ([[$j, $i], [$j + 1, $i], [$j + 1, $i + 1], [$j, $i], [$j + 1, $i + 1], [$j, $i + 1]] as [$rr, $ss]) {
                $theta = $rr * M_PI / $rings;
                $phi = $ss * 2.0 * M_PI / $segments;
                $x = sin($theta) * cos($phi);
                $y = cos($theta);
                $z = sin($theta) * sin($phi);
                $buffer->push($x * $radius); $buffer->push($y * $radius); $buffer->push($z * $radius);
                $buffer->push($x); $buffer->push($y); $buffer->push($z);
                $buffer->push($ss / $segments); $buffer->push($rr / $rings);
                $buffer->push(-sin($phi)); $buffer->push(0.0); $buffer->push(cos($phi)); $buffer->push(1.0);
            }
        }
    }
    return $buffer;
}

/**
 * Generate a cube
 */
function genCube(float $size = 1.0): FloatBuffer
{
    $buffer = new FloatBuffer();
    $h = $size * 0.5;

    $faces = [
        // front (z+)
        [[-$h,-$h,$h], [$h,-$h,$h], [$h,$h,$h], [-$h,-$h,$h], [$h,$h,$h], [-$h,$h,$h], [0,0,1], [1,0,0]],
        // back (z-)
        [[$h,-$h,-$h], [-$h,-$h,-$h], [-$h,$h,-$h], [$h,-$h,-$h], [-$h,$h,-$h], [$h,$h,-$h], [0,0,-1], [-1,0,0]],
        // right (x+)
        [[$h,-$h,$h], [$h,-$h,-$h], [$h,$h,-$h], [$h,-$h,$h], [$h,$h,-$h], [$h,$h,$h], [1,0,0], [0,0,-1]],
        // left (x-)
        [[-$h,-$h,-$h], [-$h,-$h,$h], [-$h,$h,$h], [-$h,-$h,-$h], [-$h,$h,$h], [-$h,$h,-$h], [-1,0,0], [0,0,1]],
        // top (y+)
        [[-$h,$h,$h], [$h,$h,$h], [$h,$h,-$h], [-$h,$h,$h], [$h,$h,-$h], [-$h,$h,-$h], [0,1,0], [1,0,0]],
        // bottom (y-)
        [[-$h,-$h,-$h], [$h,-$h,-$h], [$h,-$h,$h], [-$h,-$h,-$h], [$h,-$h,$h], [-$h,-$h,$h], [0,-1,0], [1,0,0]],
    ];

    foreach ($faces as $face) {
        $n = $face[6];
        $t = $face[7];
        for ($i = 0; $i < 6; $i++) {
            $p = $face[$i];
            $buffer->push($p[0]); $buffer->push($p[1]); $buffer->push($p[2]); // pos
            $buffer->push($n[0]); $buffer->push($n[1]); $buffer->push($n[2]); // normal
            $buffer->push(0.0); $buffer->push(0.0); // uv (simplified)
            $buffer->push($t[0]); $buffer->push($t[1]); $buffer->push($t[2]); $buffer->push(1.0); // tangent
        }
    }

    return $buffer;
}

/**
 * Generate a floor plane
 */
function genPlane(float $size = 20.0): FloatBuffer
{
    $buffer = new FloatBuffer();
    $h = $size * 0.5;
    $verts = [
        [-$h, 0, -$h], [$h, 0, -$h], [$h, 0, $h],
        [-$h, 0, -$h], [$h, 0, $h], [-$h, 0, $h],
    ];
    foreach ($verts as $p) {
        $buffer->push($p[0]); $buffer->push($p[1]); $buffer->push($p[2]);
        $buffer->push(0); $buffer->push(1); $buffer->push(0);
        $buffer->push(($p[0] + $h) / $size); $buffer->push(($p[2] + $h) / $size);
        $buffer->push(1); $buffer->push(0); $buffer->push(0); $buffer->push(1);
    }
    return $buffer;
}

$quickstart = new Quickstart(function (QuickstartOptions $app) use (&$state, $container) {
    $app->container = $container;
    $app->windowTitle = 'VISU — Multi-Light PBR Demo';

    $app->ready = function (QuickstartApp $app) use (&$state) {
        $state->models = new ModelCollection();
        $state->renderingSystem = new Rendering3DSystem($app->gl, $app->shaders, $state->models);
        $state->cameraSystem = new VISUCameraSystem($app->input, $app->dispatcher);

        $app->bindSystems([
            $state->renderingSystem,
            $state->cameraSystem,
        ]);

        // -- create models --

        // gold sphere
        $goldMat = new Material('gold', new Vec4(1.0, 0.766, 0.336, 1.0), 1.0, 0.3);
        $goldMesh = new Mesh3D($app->gl, $goldMat, new AABB(new Vec3(-0.8, -0.8, -0.8), new Vec3(0.8, 0.8, 0.8)));
        $goldMesh->uploadVertices(genSphere(32, 16, 0.8));
        $goldModel = new Model3D('gold_sphere');
        $goldModel->addMesh($goldMesh);
        $goldModel->recalculateAABB();
        $state->models->add($goldModel);

        // silver cube
        $silverMat = new Material('silver', new Vec4(0.95, 0.93, 0.88, 1.0), 1.0, 0.15);
        $silverMesh = new Mesh3D($app->gl, $silverMat, new AABB(new Vec3(-0.6, -0.6, -0.6), new Vec3(0.6, 0.6, 0.6)));
        $silverMesh->uploadVertices(genCube(1.2));
        $silverModel = new Model3D('silver_cube');
        $silverModel->addMesh($silverMesh);
        $silverModel->recalculateAABB();
        $state->models->add($silverModel);

        // plastic sphere
        $plasticMat = new Material('plastic', new Vec4(0.1, 0.4, 0.9, 1.0), 0.0, 0.4);
        $plasticMesh = new Mesh3D($app->gl, $plasticMat, new AABB(new Vec3(-0.7, -0.7, -0.7), new Vec3(0.7, 0.7, 0.7)));
        $plasticMesh->uploadVertices(genSphere(32, 16, 0.7));
        $plasticModel = new Model3D('plastic_sphere');
        $plasticModel->addMesh($plasticMesh);
        $plasticModel->recalculateAABB();
        $state->models->add($plasticModel);

        // emissive sphere
        $emissiveMat = new Material('emissive', new Vec4(0.05, 0.05, 0.05, 1.0), 0.0, 0.9);
        $emissiveMat->emissiveColor = new Vec3(2.0, 0.5, 0.1);
        $emissiveMesh = new Mesh3D($app->gl, $emissiveMat, new AABB(new Vec3(-0.5, -0.5, -0.5), new Vec3(0.5, 0.5, 0.5)));
        $emissiveMesh->uploadVertices(genSphere(24, 12, 0.5));
        $emissiveModel = new Model3D('emissive_sphere');
        $emissiveModel->addMesh($emissiveMesh);
        $emissiveModel->recalculateAABB();
        $state->models->add($emissiveModel);

        // floor
        $floorMat = new Material('floor', new Vec4(0.4, 0.4, 0.45, 1.0), 0.0, 0.9);
        $floorMesh = new Mesh3D($app->gl, $floorMat, new AABB(new Vec3(-10, -0.01, -10), new Vec3(10, 0.01, 10)));
        $floorMesh->uploadVertices(genPlane(20.0));
        $floorModel = new Model3D('floor');
        $floorModel->addMesh($floorMesh);
        $floorModel->recalculateAABB();
        $state->models->add($floorModel);

        // small sphere for light markers
        $lightMarkerMat = new Material('light_marker', new Vec4(1, 1, 1, 1), 0.0, 1.0);
        $lightMarkerMat->emissiveColor = new Vec3(5.0, 5.0, 5.0);
        $lightMarkerMesh = new Mesh3D($app->gl, $lightMarkerMat, new AABB(new Vec3(-0.1, -0.1, -0.1), new Vec3(0.1, 0.1, 0.1)));
        $lightMarkerMesh->uploadVertices(genSphere(12, 6, 0.1));
        $lightMarkerModel = new Model3D('light_marker');
        $lightMarkerModel->addMesh($lightMarkerMesh);
        $lightMarkerModel->recalculateAABB();
        $state->models->add($lightMarkerModel);
    };

    $app->initializeScene = function (QuickstartApp $app) use (&$state) {
        $state->cameraSystem->spawnDefaultFlyingCamera($app->entities, new Vec3(0.0, 5.0, 12.0));

        // sun (dimmed, let point lights be the main illumination)
        $sun = $app->entities->getSingleton(DirectionalLightComponent::class);
        $sun->direction = new Vec3(0.3, 1.0, 0.5);
        $sun->direction->normalize();
        $sun->intensity = 0.3;

        // scene objects
        $objects = [
            ['gold_sphere', 0.0, 1.0, 0.0],
            ['silver_cube', -3.0, 0.8, -2.0],
            ['plastic_sphere', 3.0, 0.9, -1.5],
            ['emissive_sphere', 0.0, 0.7, -3.5],
        ];
        foreach ($objects as [$model, $x, $y, $z]) {
            $entity = $app->entities->create();
            $app->entities->attach($entity, new MeshRendererComponent($model));
            $transform = $app->entities->attach($entity, new Transform());
            $transform->position->x = $x;
            $transform->position->y = $y;
            $transform->position->z = $z;
            $transform->markDirty();
        }

        // floor
        $floor = $app->entities->create();
        $app->entities->attach($floor, new MeshRendererComponent('floor'));
        $app->entities->attach($floor, new Transform());

        // orbiting point lights (8 lights in a ring)
        $numLights = 8;
        $lightColors = [
            new Vec3(1.0, 0.3, 0.3),  // red
            new Vec3(0.3, 1.0, 0.3),  // green
            new Vec3(0.3, 0.3, 1.0),  // blue
            new Vec3(1.0, 1.0, 0.3),  // yellow
            new Vec3(1.0, 0.3, 1.0),  // magenta
            new Vec3(0.3, 1.0, 1.0),  // cyan
            new Vec3(1.0, 0.6, 0.2),  // orange
            new Vec3(0.8, 0.4, 1.0),  // purple
        ];

        for ($i = 0; $i < $numLights; $i++) {
            $light = $app->entities->create();
            $lightComp = new PointLightComponent($lightColors[$i], 3.0, 12.0);
            $lightComp->setAttenuationFromRange();
            $app->entities->attach($light, $lightComp);
            $transform = $app->entities->attach($light, new Transform());
            $transform->position->y = 2.0;
            $transform->markDirty();
            $state->lightEntities[] = $light;

            // light marker (small emissive sphere at light position)
            $marker = $app->entities->create();
            $markerMat = new Material("light_marker_{$i}", new Vec4(0.01, 0.01, 0.01, 1.0), 0.0, 1.0);
            $markerMat->emissiveColor = $lightColors[$i] * 5.0;
            $markerRenderer = new MeshRendererComponent('light_marker');
            $markerRenderer->materialOverride = $markerMat;
            $app->entities->attach($marker, $markerRenderer);
            $markerTransform = $app->entities->attach($marker, new Transform());
            $markerTransform->setParent($app->entities, $light);
        }
    };

    $app->update = function (QuickstartApp $app) use (&$state) {
        $app->updateSystem($state->cameraSystem);

        // orbit the lights around the scene
        $time = glfwGetTime();
        $numLights = count($state->lightEntities);
        $radius = 6.0;

        for ($i = 0; $i < $numLights; $i++) {
            $angle = ($i / $numLights) * M_PI * 2.0 + $time * 0.5;
            $transform = $app->entities->get($state->lightEntities[$i], Transform::class);
            $transform->position->x = cos($angle) * $radius;
            $transform->position->z = sin($angle) * $radius;
            $transform->position->y = 2.0 + sin($time * 0.8 + $i) * 1.0;
            $transform->markDirty();
        }
    };

    $app->render = function (QuickstartApp $app, RenderContext $context, RenderTargetResource $target) use (&$state) {
        $state->renderingSystem->setRenderTarget($target);
        $app->renderSystem($state->cameraSystem, $context);
        $app->renderSystem($state->renderingSystem, $context);
    };
});

$quickstart->run();
