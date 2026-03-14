<?php
/**
 * Office Scene Demo
 *
 * Loads the office_level1.json scene and renders all entities as colored rectangles
 * using NanoVG. Demonstrates scene system, UIInterpreter (JSON-driven HUD), and data binding.
 *
 * Run: php examples/office_demo/office_demo.php
 */

use GL\Math\Vec2;
use GL\Math\Vec4;
use GL\VectorGraphics\{VGContext, VGColor};
use VISU\Component\NameComponent;
use VISU\Component\SpriteRenderer;
use VISU\Component\SpriteAnimator;
use VISU\ECS\ComponentRegistry;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\RenderTarget;
use VISU\Graphics\SortingLayer;
use VISU\OS\Input;
use VISU\Quickstart;
use VISU\Quickstart\QuickstartApp;
use VISU\Quickstart\QuickstartOptions;
use VISU\Scene\SceneLoader;
use VISU\Signal\SignalQueue;
use VISU\Signals\Input\ScrollSignal;
use VISU\FlyUI\FlyUI;
use VISU\FlyUI\FUILayoutFlow;
use VISU\UI\UIInterpreter;
use VISU\UI\UIDataContext;
use VISU\Signals\UI\UIEventSignal;

$container = require __DIR__ . '/../bootstrap.php';

// --- Setup Component Registry ---
$componentRegistry = new ComponentRegistry();
$componentRegistry->register('SpriteRenderer', SpriteRenderer::class);
$componentRegistry->register('SpriteAnimator', SpriteAnimator::class);

$sceneLoader = new SceneLoader($componentRegistry);
$sortingLayer = new SortingLayer();

// --- Color mapping for sprite types (since we have no actual textures) ---
$spriteColors = [
    'sprites/floor_large.png'   => VGColor::rgb(0.85, 0.82, 0.75),   // warm beige floor
    'sprites/wall_h.png'        => VGColor::rgb(0.55, 0.52, 0.48),   // gray walls
    'sprites/wall_v.png'        => VGColor::rgb(0.55, 0.52, 0.48),
    'sprites/desk.png'          => VGColor::rgb(0.60, 0.45, 0.30),   // brown desks
    'sprites/employee.png'      => VGColor::rgb(0.30, 0.55, 0.85),   // blue employees
    'sprites/chair.png'         => VGColor::rgb(0.25, 0.25, 0.25),   // dark chairs
    'sprites/server.png'        => VGColor::rgb(0.20, 0.20, 0.30),   // dark blue servers
    'sprites/led_green.png'     => VGColor::rgb(0.10, 0.90, 0.20),   // green LEDs
    'sprites/coffee_machine.png'=> VGColor::rgb(0.50, 0.35, 0.25),   // coffee brown
    'sprites/fridge.png'        => VGColor::rgb(0.80, 0.82, 0.85),   // silver fridge
    'sprites/table_round.png'   => VGColor::rgb(0.65, 0.50, 0.35),   // lighter wood
    'sprites/plant.png'         => VGColor::rgb(0.20, 0.65, 0.25),   // green plants
    'sprites/water_cooler.png'  => VGColor::rgb(0.60, 0.78, 0.90),   // light blue
    'sprites/whiteboard.png'    => VGColor::rgb(0.95, 0.95, 0.98),   // white
    'sprites/poster.png'        => VGColor::rgb(0.90, 0.50, 0.30),   // orange poster
    'sprites/trashbin.png'      => VGColor::rgb(0.45, 0.45, 0.45),   // gray bin
];

// Camera state
$camX = 0.0;
$camY = 0.0;
$camZoom = 1.5;
$dragStartX = null;
$dragStartY = null;
$dragCamStart = null;

/** @var SignalQueue<ScrollSignal>|null $scrollQueue */
$scrollQueue = null;

// --- UI Interpreter for JSON-driven HUD ---
/** @var UIInterpreter|null $uiInterpreter */
$uiInterpreter = null;
$gameData = new UIDataContext();
$gameData->setAll([
    'economy.money' => 12500,
    'company.employees' => 9,
    'company.morale' => 0.82,
    'project.progress' => 0.35,
]);

$quickstart = new Quickstart(function(QuickstartOptions $app) use($sceneLoader, $componentRegistry, &$scrollQueue, $sortingLayer, $spriteColors, &$camX, &$camY, &$camZoom, &$dragStartX, &$dragStartY, &$dragCamStart, &$uiInterpreter, $gameData)
{
    $app->windowTitle = 'Office Demo';
    $app->windowWidth = 1280;
    $app->windowHeight = 720;

    $app->ready = function(QuickstartApp $app) use(&$scrollQueue, &$uiInterpreter, $gameData) {
        // Create scroll signal queue for zoom
        $scrollQueue = $app->dispatcher->createSignalQueue(Input::EVENT_SCROLL);

        // Create UI interpreter with data binding
        $uiInterpreter = new UIInterpreter($app->dispatcher, $gameData);

        // Listen for UI events from JSON buttons
        $app->dispatcher->register('ui.event', function(UIEventSignal $signal) use ($gameData) {
            echo "UI Event: {$signal->event}\n";
            if ($signal->event === 'ui.hire_employee') {
                $employees = (int) $gameData->get('company.employees', 0);
                $gameData->set('company.employees', $employees + 1);
                $money = (int) $gameData->get('economy.money', 0);
                $gameData->set('economy.money', $money - 1000);
            }
        });
    };

    $app->initializeScene = function(QuickstartApp $app) use($sceneLoader) {
        // Register components
        $app->entities->registerComponent(Transform::class);
        $app->entities->registerComponent(NameComponent::class);
        $app->entities->registerComponent(SpriteRenderer::class);
        $app->entities->registerComponent(SpriteAnimator::class);

        // Load the office scene
        $scenePath = __DIR__ . '/scenes/office_level1.json';
        $entityIds = $sceneLoader->loadFile($scenePath, $app->entities);
        echo "Loaded " . count($entityIds) . " entities from office_level1.json\n";
    };

    $app->update = function(QuickstartApp $app) use(&$camX, &$camY, &$camZoom, &$dragStartX, &$dragStartY, &$dragCamStart, &$scrollQueue) {
        // Scroll to zoom via signal queue
        if ($scrollQueue !== null) {
            foreach ($scrollQueue->poll() as $signal) {
                /** @var ScrollSignal $signal */
                $camZoom += $signal->y * 0.15;
                $camZoom = max(0.3, min(5.0, $camZoom));
            }
        }

        // Middle mouse / right mouse drag to pan
        $cursorPos = $app->input->getCursorPosition();
        if ($app->input->isMouseButtonPressed(GLFW_MOUSE_BUTTON_RIGHT) || $app->input->isMouseButtonPressed(GLFW_MOUSE_BUTTON_MIDDLE)) {
            if ($dragStartX === null) {
                $dragStartX = $cursorPos->x;
                $dragStartY = $cursorPos->y;
                $dragCamStart = [$camX, $camY];
            }
            $dx = ($cursorPos->x - $dragStartX) / $camZoom;
            $dy = ($cursorPos->y - $dragStartY) / $camZoom;
            $camX = $dragCamStart[0] - $dx;
            $camY = $dragCamStart[1] - $dy;
        } else {
            $dragStartX = null;
            $dragStartY = null;
            $dragCamStart = null;
        }

        // Arrow keys / WASD to pan
        $panSpeed = 3.0 / $camZoom;
        if ($app->input->isKeyPressed(GLFW_KEY_W) || $app->input->isKeyPressed(GLFW_KEY_UP)) $camY -= $panSpeed;
        if ($app->input->isKeyPressed(GLFW_KEY_S) || $app->input->isKeyPressed(GLFW_KEY_DOWN)) $camY += $panSpeed;
        if ($app->input->isKeyPressed(GLFW_KEY_A) || $app->input->isKeyPressed(GLFW_KEY_LEFT)) $camX -= $panSpeed;
        if ($app->input->isKeyPressed(GLFW_KEY_D) || $app->input->isKeyPressed(GLFW_KEY_RIGHT)) $camX += $panSpeed;
    };

    $app->draw = function(QuickstartApp $app, RenderContext $context, RenderTarget $target) use(&$camX, &$camY, &$camZoom, $sortingLayer, $spriteColors, &$uiInterpreter, $gameData)
    {
        $vg = $app->vg;
        $screenW = $target->effectiveWidth();
        $screenH = $target->effectiveHeight();

        // Clear background
        $vg->beginPath();
        $vg->rect(0, 0, $screenW, $screenH);
        $vg->fillColor(VGColor::rgb(0.12, 0.12, 0.15));
        $vg->fill();

        $cx = $screenW / 2.0;
        $cy = $screenH / 2.0;

        // Collect and sort sprites
        $sprites = [];
        foreach ($app->entities->view(SpriteRenderer::class) as $entityId => $sprite) {
            $transform = $app->entities->tryGet($entityId, Transform::class);
            if ($transform === null) continue;

            // Compute world position (walk parent chain)
            $worldX = $transform->position->x;
            $worldY = $transform->position->y;
            $parent = $transform->parent;
            while ($parent !== null) {
                $parentTransform = $app->entities->tryGet($parent, Transform::class);
                if ($parentTransform === null) break;
                $worldX += $parentTransform->position->x;
                $worldY += $parentTransform->position->y;
                $parent = $parentTransform->parent;
            }

            $sortKey = $sortingLayer->getSortKey($sprite->sortingLayer, $sprite->orderInLayer, $worldY);
            $sprites[] = [
                'key' => $sortKey,
                'sprite' => $sprite,
                'worldX' => $worldX,
                'worldY' => $worldY,
                'entityId' => $entityId,
            ];
        }

        usort($sprites, fn($a, $b) => $a['key'] <=> $b['key']);

        // Render sprites as colored rectangles
        foreach ($sprites as $entry) {
            $sprite = $entry['sprite'];
            $worldX = $entry['worldX'];
            $worldY = $entry['worldY'];

            $w = ($sprite->width > 0 ? $sprite->width : 32);
            $h = ($sprite->height > 0 ? $sprite->height : 32);

            $drawW = $w * $camZoom;
            $drawH = $h * $camZoom;
            $drawX = $cx + ($worldX - $camX) * $camZoom - $drawW / 2;
            $drawY = $cy + ($worldY - $camY) * $camZoom - $drawH / 2;

            // Get color for this sprite type
            $color = $spriteColors[$sprite->sprite] ?? VGColor::rgb(0.7, 0.3, 0.7);

            $vg->beginPath();
            $vg->roundedRect($drawX, $drawY, $drawW, $drawH, 2.0 * $camZoom);
            $vg->fillColor($color);
            $vg->fill();

            // Draw a subtle border
            $vg->strokeColor(VGColor::rgba(0, 0, 0, 0.2));
            $vg->strokeWidth(1.0);
            $vg->stroke();
        }

        // --- HUD overlay from JSON ---
        if ($uiInterpreter !== null) {
            $uiInterpreter->renderFile(__DIR__ . '/ui/hud.json');
        }

        // --- Status bar ---
        FlyUI::beginLayout(new Vec4(15))
            ->flow(FUILayoutFlow::vertical)
            ->horizontalFit()
            ->verticalFit()
            ->alignBottomLeft();

        FlyUI::text(sprintf('Entities: %d | Zoom: %.1fx', count($sprites), $camZoom), VGColor::rgb(0.5, 0.5, 0.5))->fontSize(11);
        FlyUI::text('WASD/Arrows: Pan | Scroll: Zoom | RMB: Drag', VGColor::rgb(0.4, 0.4, 0.4))->fontSize(10);

        FlyUI::end();

        // --- Legend ---
        $legendItems = [
            ['Floor', VGColor::rgb(0.85, 0.82, 0.75)],
            ['Walls', VGColor::rgb(0.55, 0.52, 0.48)],
            ['Desks', VGColor::rgb(0.60, 0.45, 0.30)],
            ['Employees', VGColor::rgb(0.30, 0.55, 0.85)],
            ['Servers', VGColor::rgb(0.20, 0.20, 0.30)],
            ['Plants', VGColor::rgb(0.20, 0.65, 0.25)],
            ['Furniture', VGColor::rgb(0.65, 0.50, 0.35)],
        ];

        $legendX = $screenW - 150;
        $legendY = 15;

        $vg->fontSize(12);
        foreach ($legendItems as $i => $item) {
            $y = $legendY + $i * 20;
            $vg->beginPath();
            $vg->roundedRect($legendX, $y, 14, 14, 2);
            $vg->fillColor($item[1]);
            $vg->fill();

            $vg->fillColor(VGColor::rgb(0.8, 0.8, 0.8));
            $vg->text($legendX + 20, $y + 11, $item[0]);
        }
    };
});

$quickstart->run();
