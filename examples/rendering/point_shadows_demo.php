<?php
/**
 * Point Light Cubemap Shadows Demo
 *
 * Demonstrates point light shadows using cubemap shadow maps.
 * Shadow-casting point lights orbit around a scene with multiple objects,
 * creating dynamic omnidirectional shadows.
 *
 * Controls:
 *   WASD + Mouse   — Fly camera
 *   Shift          — Sprint
 *   1/2/3/4        — Toggle shadow-casting lights (1-4)
 *   R              — Toggle shadow resolution (256/512/1024)
 *   P              — Toggle point shadows on/off
 *   T              — Toggle directional (sun) shadows on/off
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

class PointShadowDemoState
{
    public VISUCameraSystem $cameraSystem;
    public Rendering3DSystem $renderingSystem;
    public ModelCollection $models;
    /** @var array<int> shadow-casting light entity IDs */
    public array $shadowLightEntities = [];
    /** @var array<int> non-shadow fill light entity IDs */
    public array $fillLightEntities = [];
    public int $shadowResolution = 512;
}

$state = new PointShadowDemoState;

// ── Geometry generators ──────────────────────────────────────────────

function genSpherePS(int $segments = 32, int $rings = 16, float $radius = 1.0): FloatBuffer
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

function genCubePS(float $size = 1.0): FloatBuffer
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

function genPlanePS(float $size = 20.0): FloatBuffer
{
    $buffer = new FloatBuffer();
    $h = $size * 0.5;
    $verts = [[-$h, 0, -$h], [$h, 0, -$h], [$h, 0, $h], [-$h, 0, -$h], [$h, 0, $h], [-$h, 0, $h]];
    foreach ($verts as $p) {
        $buffer->push($p[0]); $buffer->push($p[1]); $buffer->push($p[2]);
        $buffer->push(0); $buffer->push(1); $buffer->push(0);
        $buffer->push(($p[0] + $h) / $size); $buffer->push(($p[2] + $h) / $size);
        $buffer->push(1); $buffer->push(0); $buffer->push(0); $buffer->push(1);
    }
    return $buffer;
}

function genCylinderPS(float $radius = 0.3, float $height = 3.0, int $segments = 24): FloatBuffer
{
    $buffer = new FloatBuffer();
    $halfH = $height * 0.5;
    for ($i = 0; $i < $segments; $i++) {
        $a0 = ($i / $segments) * M_PI * 2;
        $a1 = (($i + 1) / $segments) * M_PI * 2;
        $x0 = cos($a0) * $radius; $z0 = sin($a0) * $radius;
        $x1 = cos($a1) * $radius; $z1 = sin($a1) * $radius;
        $nx0 = cos($a0); $nz0 = sin($a0);
        $nx1 = cos($a1); $nz1 = sin($a1);

        // Two triangles per segment (side wall)
        $verts = [
            [$x0, -$halfH, $z0, $nx0, 0, $nz0],
            [$x1, -$halfH, $z1, $nx1, 0, $nz1],
            [$x1,  $halfH, $z1, $nx1, 0, $nz1],
            [$x0, -$halfH, $z0, $nx0, 0, $nz0],
            [$x1,  $halfH, $z1, $nx1, 0, $nz1],
            [$x0,  $halfH, $z0, $nx0, 0, $nz0],
        ];
        foreach ($verts as $v) {
            $buffer->push($v[0]); $buffer->push($v[1]); $buffer->push($v[2]);
            $buffer->push($v[3]); $buffer->push($v[4]); $buffer->push($v[5]);
            $buffer->push(0.0); $buffer->push(0.0);
            $buffer->push(0.0); $buffer->push(1.0); $buffer->push(0.0); $buffer->push(1.0);
        }
    }
    return $buffer;
}

// ── App setup ────────────────────────────────────────────────────────

$quickstart = new Quickstart(function (QuickstartOptions $app) use (&$state, $container) {
    $app->container = $container;
    $app->windowTitle = 'VISU — Point Light Cubemap Shadows Demo';

    $app->ready = function (QuickstartApp $app) use (&$state) {
        $state->models = new ModelCollection();
        $state->renderingSystem = new Rendering3DSystem($app->gl, $app->shaders, $state->models);
        $state->renderingSystem->pointShadowsEnabled = true;
        $state->renderingSystem->pointShadowResolution = $state->shadowResolution;
        $state->renderingSystem->shadowsEnabled = true;
        $state->cameraSystem = new VISUCameraSystem($app->input, $app->dispatcher);

        $app->bindSystems([$state->renderingSystem, $state->cameraSystem]);

        // -- Models --

        // Floor
        $floorMat = new Material('floor', new Vec4(0.5, 0.5, 0.55, 1.0), 0.0, 0.85);
        $floorMesh = new Mesh3D($app->gl, $floorMat, new AABB(new Vec3(-15, -0.01, -15), new Vec3(15, 0.01, 15)));
        $floorMesh->uploadVertices(genPlanePS(30.0));
        $floorModel = new Model3D('floor');
        $floorModel->addMesh($floorMesh);
        $floorModel->recalculateAABB();
        $state->models->add($floorModel);

        // Central pillar (receives shadows nicely)
        $pillarMat = new Material('pillar', new Vec4(0.8, 0.75, 0.7, 1.0), 0.0, 0.6);
        $pillarMesh = new Mesh3D($app->gl, $pillarMat, new AABB(new Vec3(-0.3, -1.5, -0.3), new Vec3(0.3, 1.5, 0.3)));
        $pillarMesh->uploadVertices(genCylinderPS(0.3, 3.0));
        $pillarModel = new Model3D('pillar');
        $pillarModel->addMesh($pillarMesh);
        $pillarModel->recalculateAABB();
        $state->models->add($pillarModel);

        // Cube (shadow caster + receiver)
        $cubeMat = new Material('cube', new Vec4(0.9, 0.3, 0.2, 1.0), 0.0, 0.5);
        $cubeMesh = new Mesh3D($app->gl, $cubeMat, new AABB(new Vec3(-0.5, -0.5, -0.5), new Vec3(0.5, 0.5, 0.5)));
        $cubeMesh->uploadVertices(genCubePS(1.0));
        $cubeModel = new Model3D('cube');
        $cubeModel->addMesh($cubeMesh);
        $cubeModel->recalculateAABB();
        $state->models->add($cubeModel);

        // Sphere
        $sphereMat = new Material('sphere', new Vec4(0.2, 0.5, 0.9, 1.0), 0.3, 0.3);
        $sphereMesh = new Mesh3D($app->gl, $sphereMat, new AABB(new Vec3(-0.6, -0.6, -0.6), new Vec3(0.6, 0.6, 0.6)));
        $sphereMesh->uploadVertices(genSpherePS(32, 16, 0.6));
        $sphereModel = new Model3D('sphere');
        $sphereModel->addMesh($sphereMesh);
        $sphereModel->recalculateAABB();
        $state->models->add($sphereModel);

        // Wall (large shadow receiver behind objects)
        $wallMat = new Material('wall', new Vec4(0.6, 0.6, 0.65, 1.0), 0.0, 0.9);
        $wallMesh = new Mesh3D($app->gl, $wallMat, new AABB(new Vec3(-0.1, -3, -5), new Vec3(0.1, 3, 5)));
        $wallMesh->uploadVertices(genCubePS(1.0));
        $wallModel = new Model3D('wall');
        $wallModel->addMesh($wallMesh);
        $wallModel->recalculateAABB();
        $state->models->add($wallModel);

        // Small emissive marker for light positions
        $markerMat = new Material('marker', new Vec4(1, 1, 1, 1), 0.0, 1.0);
        $markerMat->emissiveColor = new Vec3(5.0, 5.0, 5.0);
        $markerMesh = new Mesh3D($app->gl, $markerMat, new AABB(new Vec3(-0.08, -0.08, -0.08), new Vec3(0.08, 0.08, 0.08)));
        $markerMesh->uploadVertices(genSpherePS(8, 4, 0.08));
        $markerModel = new Model3D('light_marker');
        $markerModel->addMesh($markerMesh);
        $markerModel->recalculateAABB();
        $state->models->add($markerModel);
    };

    $app->initializeScene = function (QuickstartApp $app) use (&$state) {
        // Camera
        $state->cameraSystem->spawnDefaultFlyingCamera($app->entities, new Vec3(0.0, 5.0, 12.0));

        // Dim sun (let point lights dominate)
        $sun = $app->entities->getSingleton(DirectionalLightComponent::class);
        $sun->direction = new Vec3(0.3, 1.0, 0.5);
        $sun->direction->normalize();
        $sun->intensity = 0.15;

        // Floor
        $floor = $app->entities->create();
        $app->entities->attach($floor, new MeshRendererComponent('floor'));
        $app->entities->attach($floor, new Transform());

        // Central pillar
        $pillar = $app->entities->create();
        $app->entities->attach($pillar, new MeshRendererComponent('pillar'));
        $t = $app->entities->attach($pillar, new Transform());
        $t->position->y = 1.5;
        $t->markDirty();

        // Surrounding cubes (shadow casters)
        $cubePositions = [
            [-3.0, 0.5, -3.0], [3.0, 0.5, -3.0],
            [-3.0, 0.5, 3.0],  [3.0, 0.5, 3.0],
            [0.0, 0.5, -5.0],  [0.0, 0.5, 5.0],
        ];
        foreach ($cubePositions as [$x, $y, $z]) {
            $e = $app->entities->create();
            $app->entities->attach($e, new MeshRendererComponent('cube'));
            $t = $app->entities->attach($e, new Transform());
            $t->position->x = $x; $t->position->y = $y; $t->position->z = $z;
            $t->markDirty();
        }

        // Spheres
        $spherePositions = [[-2.0, 0.6, 0.0], [2.0, 0.6, 0.0], [0.0, 0.6, -2.0], [0.0, 0.6, 2.0]];
        foreach ($spherePositions as [$x, $y, $z]) {
            $e = $app->entities->create();
            $app->entities->attach($e, new MeshRendererComponent('sphere'));
            $t = $app->entities->attach($e, new Transform());
            $t->position->x = $x; $t->position->y = $y; $t->position->z = $z;
            $t->markDirty();
        }

        // Back wall (receives projected shadows)
        $wall = $app->entities->create();
        $app->entities->attach($wall, new MeshRendererComponent('wall'));
        $t = $app->entities->attach($wall, new Transform());
        $t->position->z = -7.0;
        $t->position->y = 3.0;
        $t->scale = new Vec3(1.0, 6.0, 10.0);
        $t->markDirty();

        // ── Shadow-casting point lights (4 orbiting) ──
        $shadowColors = [
            new Vec3(1.0, 0.4, 0.2),  // warm orange
            new Vec3(0.2, 0.6, 1.0),  // cool blue
            new Vec3(0.3, 1.0, 0.4),  // green
            new Vec3(1.0, 0.8, 0.3),  // yellow
        ];

        for ($i = 0; $i < 4; $i++) {
            $e = $app->entities->create();
            $light = new PointLightComponent($shadowColors[$i], 4.0, 18.0);
            $light->castsShadows = true;
            $light->setAttenuationFromRange();
            $app->entities->attach($e, $light);
            $t = $app->entities->attach($e, new Transform());
            $t->position->y = 3.0;
            $t->markDirty();
            $state->shadowLightEntities[] = $e;

            // Marker sphere
            $marker = $app->entities->create();
            $markerMat = new Material("marker_{$i}", new Vec4(0.01, 0.01, 0.01, 1.0), 0.0, 1.0);
            $markerMat->emissiveColor = $shadowColors[$i] * 8.0;
            $markerRenderer = new MeshRendererComponent('light_marker');
            $markerRenderer->materialOverride = $markerMat;
            $markerRenderer->castsShadows = false;
            $app->entities->attach($marker, $markerRenderer);
            $mt = $app->entities->attach($marker, new Transform());
            $mt->setParent($app->entities, $e);
        }

        // ── Non-shadow fill lights (4 stationary, dimmer) ──
        $fillPositions = [
            [5.0, 1.0, 5.0], [-5.0, 1.0, 5.0],
            [5.0, 1.0, -5.0], [-5.0, 1.0, -5.0],
        ];
        foreach ($fillPositions as $i => [$x, $y, $z]) {
            $e = $app->entities->create();
            $light = new PointLightComponent(new Vec3(0.8, 0.8, 0.9), 1.0, 10.0);
            // castsShadows stays false (fill lights)
            $light->setAttenuationFromRange();
            $app->entities->attach($e, $light);
            $t = $app->entities->attach($e, new Transform());
            $t->position->x = $x; $t->position->y = $y; $t->position->z = $z;
            $t->markDirty();
            $state->fillLightEntities[] = $e;
        }
    };

    $app->update = function (QuickstartApp $app) use (&$state) {
        $app->updateSystem($state->cameraSystem);

        $time = glfwGetTime();

        // Orbit shadow lights at different heights and speeds
        for ($i = 0; $i < count($state->shadowLightEntities); $i++) {
            $entity = $state->shadowLightEntities[$i];
            $light = $app->entities->get($entity, PointLightComponent::class);

            // Skip disabled lights
            if (!$light->castsShadows && $light->intensity === 0.0) {
                continue;
            }

            $angle = ($i / 4.0) * M_PI * 2.0 + $time * (0.3 + $i * 0.1);
            $radius = 4.0 + sin($time * 0.5 + $i) * 1.5;
            $height = 2.5 + sin($time * 0.7 + $i * 1.5) * 1.5;

            $transform = $app->entities->get($entity, Transform::class);
            $transform->position->x = cos($angle) * $radius;
            $transform->position->z = sin($angle) * $radius;
            $transform->position->y = $height;
            $transform->markDirty();
        }

        // Toggle individual shadow lights with keys 1-4
        for ($i = 0; $i < 4; $i++) {
            if ($app->input->isKeyPressed(GLFW_KEY_1 + $i)) {
                $entity = $state->shadowLightEntities[$i];
                $light = $app->entities->get($entity, PointLightComponent::class);
                $light->castsShadows = !$light->castsShadows;
            }
        }

        // Toggle point shadows globally with P
        if ($app->input->isKeyPressed(GLFW_KEY_P)) {
            $state->renderingSystem->pointShadowsEnabled = !$state->renderingSystem->pointShadowsEnabled;
        }

        // Toggle sun shadows with T
        if ($app->input->isKeyPressed(GLFW_KEY_T)) {
            $state->renderingSystem->shadowsEnabled = !$state->renderingSystem->shadowsEnabled;
        }

        // Cycle shadow resolution with R
        if ($app->input->isKeyPressed(GLFW_KEY_R)) {
            $resolutions = [256, 512, 1024];
            $idx = array_search($state->shadowResolution, $resolutions);
            $state->shadowResolution = $resolutions[($idx + 1) % count($resolutions)];
            $state->renderingSystem->pointShadowResolution = $state->shadowResolution;
        }
    };

    $app->render = function (QuickstartApp $app, RenderContext $context, RenderTargetResource $target) use (&$state) {
        $state->renderingSystem->setRenderTarget($target);
        $app->renderSystem($state->cameraSystem, $context);
        $app->renderSystem($state->renderingSystem, $context);
    };
});

$quickstart->run();
