<?php
/**
 * 3D Camera Demo
 *
 * Demonstrates Orbit, First-Person, and Third-Person camera modes.
 * Press 1/2/3 to switch between modes.
 * Press F to toggle follow target movement (third-person mode).
 */

use GL\Buffer\FloatBuffer;
use GL\Math\{GLM, Vec3, Vec4};
use VISU\Component\Camera3DMode;
use VISU\Component\DirectionalLightComponent;
use VISU\Component\MeshRendererComponent;
use VISU\Geo\AABB;
use VISU\Geo\Transform;
use VISU\Graphics\Material;
use VISU\Graphics\Mesh3D;
use VISU\Graphics\Model3D;
use VISU\Graphics\ModelCollection;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\OS\Key;
use VISU\Quickstart;
use VISU\Quickstart\QuickstartApp;
use VISU\Quickstart\QuickstartOptions;
use VISU\System\Camera3DSystem;
use VISU\System\Rendering3DSystem;

$container = require __DIR__ . '/../bootstrap.php';

class CameraDemoState
{
    public Camera3DSystem $cameraSystem;
    public Rendering3DSystem $renderingSystem;
    public ModelCollection $models;
    public int $followEntity = 0;
    public bool $followMoving = true;
}

$state = new CameraDemoState;

function genSphereDemo(int $segments = 24, int $rings = 12, float $radius = 1.0): FloatBuffer
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

function genPlaneDemo(float $size = 40.0): FloatBuffer
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

function genCubeDemo(float $size = 1.0): FloatBuffer
{
    $buffer = new FloatBuffer();
    $h = $size * 0.5;
    $faces = [
        [[-$h,-$h,$h], [$h,-$h,$h], [$h,$h,$h], [-$h,-$h,$h], [$h,$h,$h], [-$h,$h,$h], [0,0,1], [1,0,0]],
        [[$h,-$h,-$h], [-$h,-$h,-$h], [-$h,$h,-$h], [$h,-$h,-$h], [-$h,$h,-$h], [$h,$h,-$h], [0,0,-1], [-1,0,0]],
        [[$h,-$h,$h], [$h,-$h,-$h], [$h,$h,-$h], [$h,-$h,$h], [$h,$h,-$h], [$h,$h,$h], [1,0,0], [0,0,-1]],
        [[-$h,-$h,-$h], [-$h,-$h,$h], [-$h,$h,$h], [-$h,-$h,-$h], [-$h,$h,$h], [-$h,$h,-$h], [-1,0,0], [0,0,1]],
        [[-$h,$h,$h], [$h,$h,$h], [$h,$h,-$h], [-$h,$h,$h], [$h,$h,-$h], [-$h,$h,-$h], [0,1,0], [1,0,0]],
        [[-$h,-$h,-$h], [$h,-$h,-$h], [$h,-$h,$h], [-$h,-$h,-$h], [$h,-$h,$h], [-$h,-$h,$h], [0,-1,0], [1,0,0]],
    ];
    foreach ($faces as $face) {
        $n = $face[6]; $t = $face[7];
        for ($i = 0; $i < 6; $i++) {
            $p = $face[$i];
            $buffer->push($p[0]); $buffer->push($p[1]); $buffer->push($p[2]);
            $buffer->push($n[0]); $buffer->push($n[1]); $buffer->push($n[2]);
            $buffer->push(0.0); $buffer->push(0.0);
            $buffer->push($t[0]); $buffer->push($t[1]); $buffer->push($t[2]); $buffer->push(1.0);
        }
    }
    return $buffer;
}

$quickstart = new Quickstart(function (QuickstartOptions $app) use (&$state, $container) {
    $app->container = $container;
    $app->windowTitle = 'VISU — 3D Camera Demo (1=Orbit, 2=FP, 3=TP, F=Toggle follow)';

    $app->ready = function (QuickstartApp $app) use (&$state) {
        $state->models = new ModelCollection();
        $state->renderingSystem = new Rendering3DSystem($app->gl, $app->shaders, $state->models);
        $state->cameraSystem = new Camera3DSystem($app->input, $app->dispatcher);

        $app->bindSystems([
            $state->renderingSystem,
            $state->cameraSystem,
        ]);

        // floor
        $floorMat = new Material('floor', new Vec4(0.35, 0.35, 0.4, 1.0), 0.0, 0.9);
        $floorMesh = new Mesh3D($app->gl, $floorMat, new AABB(new Vec3(-20, -0.01, -20), new Vec3(20, 0.01, 20)));
        $floorMesh->uploadVertices(genPlaneDemo(40.0));
        $floorModel = new Model3D('floor');
        $floorModel->addMesh($floorMesh);
        $floorModel->recalculateAABB();
        $state->models->add($floorModel);

        // sphere (follow target)
        $sphereMat = new Material('sphere', new Vec4(0.9, 0.3, 0.2, 1.0), 0.0, 0.3);
        $sphereMesh = new Mesh3D($app->gl, $sphereMat, new AABB(new Vec3(-0.5, -0.5, -0.5), new Vec3(0.5, 0.5, 0.5)));
        $sphereMesh->uploadVertices(genSphereDemo(24, 12, 0.5));
        $sphereModel = new Model3D('sphere');
        $sphereModel->addMesh($sphereMesh);
        $sphereModel->recalculateAABB();
        $state->models->add($sphereModel);

        // pillars
        $pillarMat = new Material('pillar', new Vec4(0.7, 0.7, 0.75, 1.0), 0.0, 0.5);
        $pillarMesh = new Mesh3D($app->gl, $pillarMat, new AABB(new Vec3(-0.3, -1, -0.3), new Vec3(0.3, 1, 0.3)));
        $pillarMesh->uploadVertices(genCubeDemo(0.6));
        $pillarModel = new Model3D('pillar');
        $pillarModel->addMesh($pillarMesh);
        $pillarModel->recalculateAABB();
        $state->models->add($pillarModel);
    };

    $app->initializeScene = function (QuickstartApp $app) use (&$state) {
        // start in orbit mode
        $state->cameraSystem->spawnCamera($app->entities, Camera3DMode::orbit, new Vec3(0.0, 5.0, 15.0));

        $sun = $app->entities->getSingleton(DirectionalLightComponent::class);
        $sun->direction = new Vec3(0.3, 1.0, 0.5);
        $sun->direction->normalize();
        $sun->intensity = 1.5;

        // floor
        $floor = $app->entities->create();
        $app->entities->attach($floor, new MeshRendererComponent('floor'));
        $app->entities->attach($floor, new Transform());

        // follow target (red sphere)
        $state->followEntity = $app->entities->create();
        $app->entities->attach($state->followEntity, new MeshRendererComponent('sphere'));
        $transform = $app->entities->attach($state->followEntity, new Transform());
        $transform->position->y = 0.5;
        $transform->markDirty();

        // scene pillars in a circle
        for ($i = 0; $i < 8; $i++) {
            $angle = ($i / 8.0) * M_PI * 2.0;
            $pillar = $app->entities->create();
            $app->entities->attach($pillar, new MeshRendererComponent('pillar'));
            $pt = $app->entities->attach($pillar, new Transform());
            $pt->position->x = cos($angle) * 8.0;
            $pt->position->y = 1.0;
            $pt->position->z = sin($angle) * 8.0;
            $pt->scale->y = 2.0;
            $pt->markDirty();
        }
    };

    $app->update = function (QuickstartApp $app) use (&$state) {
        // mode switching
        if ($app->input->hasKeyBeenPressed(Key::NUM_1)) {
            $state->cameraSystem->setMode(Camera3DMode::orbit);
        }
        if ($app->input->hasKeyBeenPressed(Key::NUM_2)) {
            $state->cameraSystem->setMode(Camera3DMode::firstPerson);
        }
        if ($app->input->hasKeyBeenPressed(Key::NUM_3)) {
            $state->cameraSystem->setMode(Camera3DMode::thirdPerson);
            // set follow target
            $comp = $app->entities->get($state->cameraSystem->getCameraEntity(), \VISU\Component\Camera3DComponent::class);
            $comp->followTarget = $state->followEntity;
        }
        if ($app->input->hasKeyBeenPressed(Key::F)) {
            $state->followMoving = !$state->followMoving;
        }

        // move follow target in a circle
        if ($state->followMoving && $state->followEntity !== 0) {
            $time = glfwGetTime();
            $transform = $app->entities->get($state->followEntity, Transform::class);
            $transform->position->x = cos($time * 0.5) * 5.0;
            $transform->position->z = sin($time * 0.5) * 5.0;
            $transform->position->y = 0.5;
            $transform->markDirty();
        }

        $app->updateSystem($state->cameraSystem);
    };

    $app->render = function (QuickstartApp $app, RenderContext $context, RenderTargetResource $target) use (&$state) {
        $state->renderingSystem->setRenderTarget($target);
        $app->renderSystem($state->cameraSystem, $context);
        $app->renderSystem($state->renderingSystem, $context);
    };
});

$quickstart->run();
