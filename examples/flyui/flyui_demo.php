<?php

use GL\Math\Vec2;
use GL\Math\Vec4;
use GL\VectorGraphics\VGColor;
use VISU\ECS\EntityRegisty;
use VISU\FlyUI\FlyUI;
use VISU\FlyUI\FUIButtonGroup;
use VISU\FlyUI\FUILayoutFlow;
use VISU\FlyUI\FUILayoutSizing;
use VISU\FlyUI\FUILayoutAlignment;
use VISU\FlyUI\Theme\FUIButtonStyle;
use VISU\FlyUI\Theme\FUIButtonGroupStyle;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\RenderTarget;
use VISU\Quickstart;
use VISU\Quickstart\QuickstartApp;
use VISU\Quickstart\QuickstartOptions;

$container = require __DIR__ . '/../bootstrap.php';

class FlyUiDemoState {
    /**
     * An array of functions that can render some FlyUI demo content
     * @var array<\Closure(RenderContext, RenderTarget, FlyUiDemoState): void> $uiDemoFunctions
     */
    public array $uiDemoFunctions = [];

    /**
     * The currently active demo
     */
    public string $currentDemo = '';

    /**
     * Stacks
     * ------------------------------------------------------------------------
     */
    public FUILayoutFlow $stackFlow = FUILayoutFlow::vertical;
    public FUILayoutSizing $verticalStackSizing = FUILayoutSizing::fill;
    public FUILayoutSizing $horizontalStackSizing = FUILayoutSizing::fill;
    public FUILayoutAlignment $stackAlignment = FUILayoutAlignment::topLeft;
}

$state = new FlyUiDemoState;

/**
 * @param string $name The name of the demo
 * @param \Closure(RenderContext, RenderTarget, FlyUiDemoState): void $func
 */
function UIDemo(string $name, \Closure $func) : void {
    global $state;
    $state->uiDemoFunctions[$name] = $func;
}

/**
 * Demo: Layout - Vertical stacking
 * 
 * ----------------------------------------------------------------------------
 */
UIDemo("Layout - Stacks", function(RenderContext $context, RenderTarget $target, FlyUiDemoState $state) : void 
{
    // we have controllable settings so we split the main container in two
    // first content / settings on the right
    FlyUI::beginLayout()
        ->verticalFill()
        ->horizontalFill()
        ->flow(FUILayoutFlow::horizontal)
        ->spacing(10);

    // left content area
    FlyUI::beginLayout()
        ->verticalFill()
        ->flow($state->stackFlow)
        ->align($state->stackAlignment)
        ->spacing(5);

    for ($i = 0; $i < 10; $i++) {
        $color = VGColor::white()->darken(($i / 10));

        $layout = FlyUI::beginLayout(new Vec4(10))
            ->verticalSizing($state->verticalStackSizing)
            ->horizontalSizing($state->horizontalStackSizing)
            ->backgroundColor($color, 3.0);

        // find a text color that contrasts well with the background
        FlyUI::text("#" . ($i + 1), $color->contrast())
            ->fontSize(12);

        FlyUI::end();
    }

    FlyUI::end(); // end left content area

    // right settings area
    FlyUI::beginLayout()
        ->horizontalFit()
        ->verticalFit()
        ->spacing(5);

    $flowString = $state->stackFlow->name;
    FlyUI::buttonGroup('Stack Flow', [
        'vertical' => 'Vertical',
        'horizontal' => 'Horizontal',
    ], $flowString, function(string $option) use(&$state) {
        if ($option === 'vertical') {
            $state->stackFlow = FUILayoutFlow::vertical;
        } else {
            $state->stackFlow = FUILayoutFlow::horizontal;
        }
    });

    FlyUI::spaceY(10);

    // sizing
    FlyUI::beginSection('Stack Sizing');
    $sizingString = $state->verticalStackSizing->name;
    FlyUI::buttonGroup('Vertical', [
        'fill' => 'Fill',
        'fit' => 'Fit',
    ], $sizingString, function(string $option) use(&$state) {
        if ($option === 'fill') {
            $state->verticalStackSizing = FUILayoutSizing::fill;
        } else {
            $state->verticalStackSizing = FUILayoutSizing::fit;
        }
    });

    $sizingString = $state->horizontalStackSizing->name;
    FlyUI::buttonGroup('Horizontal', [
        'fill' => 'Fill',
        'fit' => 'Fit',
    ], $sizingString, function(string $option) use(&$state) {
        if ($option === 'fill') {
            $state->horizontalStackSizing = FUILayoutSizing::fill;
        } else {
            $state->horizontalStackSizing = FUILayoutSizing::fit;
        }
    });

    FlyUI::spaceY(10);

    // alignment
    FlyUI::beginSection('Stack Alignment');
    
    // horizontal alignment
    $horizontalAlignment = match($state->stackAlignment) {
        FUILayoutAlignment::topLeft, FUILayoutAlignment::centerLeft, FUILayoutAlignment::bottomLeft => 'left',
        FUILayoutAlignment::topCenter, FUILayoutAlignment::center, FUILayoutAlignment::bottomCenter => 'center',
        FUILayoutAlignment::topRight, FUILayoutAlignment::centerRight, FUILayoutAlignment::bottomRight => 'right',
    };
    
    FlyUI::buttonGroup('Horizontal', [
        'left' => 'Left',
        'center' => 'Center',
        'right' => 'Right',
    ], $horizontalAlignment, function(string $option) use(&$state) {
        $state->stackAlignment = match([$state->stackAlignment, $option]) {
            [FUILayoutAlignment::topLeft, 'left'], [FUILayoutAlignment::topCenter, 'left'], [FUILayoutAlignment::topRight, 'left'] => FUILayoutAlignment::topLeft,
            [FUILayoutAlignment::topLeft, 'center'], [FUILayoutAlignment::topCenter, 'center'], [FUILayoutAlignment::topRight, 'center'] => FUILayoutAlignment::topCenter,
            [FUILayoutAlignment::topLeft, 'right'], [FUILayoutAlignment::topCenter, 'right'], [FUILayoutAlignment::topRight, 'right'] => FUILayoutAlignment::topRight,
            [FUILayoutAlignment::centerLeft, 'left'], [FUILayoutAlignment::center, 'left'], [FUILayoutAlignment::centerRight, 'left'] => FUILayoutAlignment::centerLeft,
            [FUILayoutAlignment::centerLeft, 'center'], [FUILayoutAlignment::center, 'center'], [FUILayoutAlignment::centerRight, 'center'] => FUILayoutAlignment::center,
            [FUILayoutAlignment::centerLeft, 'right'], [FUILayoutAlignment::center, 'right'], [FUILayoutAlignment::centerRight, 'right'] => FUILayoutAlignment::centerRight,
            [FUILayoutAlignment::bottomLeft, 'left'], [FUILayoutAlignment::bottomCenter, 'left'], [FUILayoutAlignment::bottomRight, 'left'] => FUILayoutAlignment::bottomLeft,
            [FUILayoutAlignment::bottomLeft, 'center'], [FUILayoutAlignment::bottomCenter, 'center'], [FUILayoutAlignment::bottomRight, 'center'] => FUILayoutAlignment::bottomCenter,
            [FUILayoutAlignment::bottomLeft, 'right'], [FUILayoutAlignment::bottomCenter, 'right'], [FUILayoutAlignment::bottomRight, 'right'] => FUILayoutAlignment::bottomRight,
        };
    })->setId('h-align-btn-group');
    
    // vertical alignment
    $verticalAlignment = match($state->stackAlignment) {
        FUILayoutAlignment::topLeft, FUILayoutAlignment::topCenter, FUILayoutAlignment::topRight => 'top',
        FUILayoutAlignment::centerLeft, FUILayoutAlignment::center, FUILayoutAlignment::centerRight => 'center',
        FUILayoutAlignment::bottomLeft, FUILayoutAlignment::bottomCenter, FUILayoutAlignment::bottomRight => 'bottom',
    };
    
    FlyUI::buttonGroup('Vertical', [
        'top' => 'Top',
        'center' => 'Center',
        'bottom' => 'Bottom',
    ], $verticalAlignment, function(string $option) use(&$state) {
        $state->stackAlignment = match([$state->stackAlignment, $option]) {
            [FUILayoutAlignment::topLeft, 'top'], [FUILayoutAlignment::centerLeft, 'top'], [FUILayoutAlignment::bottomLeft, 'top'] => FUILayoutAlignment::topLeft,
            [FUILayoutAlignment::topCenter, 'top'], [FUILayoutAlignment::center, 'top'], [FUILayoutAlignment::bottomCenter, 'top'] => FUILayoutAlignment::topCenter,
            [FUILayoutAlignment::topRight, 'top'], [FUILayoutAlignment::centerRight, 'top'], [FUILayoutAlignment::bottomRight, 'top'] => FUILayoutAlignment::topRight,
            [FUILayoutAlignment::topLeft, 'center'], [FUILayoutAlignment::centerLeft, 'center'], [FUILayoutAlignment::bottomLeft, 'center'] => FUILayoutAlignment::centerLeft,
            [FUILayoutAlignment::topCenter, 'center'], [FUILayoutAlignment::center, 'center'], [FUILayoutAlignment::bottomCenter, 'center'] => FUILayoutAlignment::center,
            [FUILayoutAlignment::topRight, 'center'], [FUILayoutAlignment::centerRight, 'center'], [FUILayoutAlignment::bottomRight, 'center'] => FUILayoutAlignment::centerRight,
            [FUILayoutAlignment::topLeft, 'bottom'], [FUILayoutAlignment::centerLeft, 'bottom'], [FUILayoutAlignment::bottomLeft, 'bottom'] => FUILayoutAlignment::bottomLeft,
            [FUILayoutAlignment::topCenter, 'bottom'], [FUILayoutAlignment::center, 'bottom'], [FUILayoutAlignment::bottomCenter, 'bottom'] => FUILayoutAlignment::bottomCenter,
            [FUILayoutAlignment::topRight, 'bottom'], [FUILayoutAlignment::centerRight, 'bottom'], [FUILayoutAlignment::bottomRight, 'bottom'] => FUILayoutAlignment::bottomRight,
        };
    })->setId('v-align-btn-group');
    
    FlyUI::end(); // end alignment section

    FlyUI::end(); // end right settings area 

    FlyUI::end(); // end main container

});

/**
 * Demo: Components - Buttons
 * 
 * ----------------------------------------------------------------------------
 */
UIDemo("Components - Buttons", function(RenderContext $context, RenderTarget $target, FlyUiDemoState $state) : void 
{
    FlyUI::beginSection('Button Styles');
    FlyUI::beginHorizontalStack();

    FlyUI::button('Primary Button', function() {
        echo "Primary Button Pressed\n";
    });

    FlyUI::button('Secondary Button', function() {
        echo "Secondary Button Pressed\n";
    })->applyStyle(FUIButtonStyle::secondary());

    FlyUI::end(); // end horizontal stack
    FlyUI::end(); // end section

    FlyUI::beginSection('Button Styles');
    FlyUI::beginHorizontalStack();

    FlyUI::button('Primary Button', function() {
        echo "Primary Button Pressed\n";
    })->setId('primary-btn2');

    FlyUI::button('Secondary Button', function() {
        echo "Secondary Button Pressed\n";
    })->applyStyle(FUIButtonStyle::secondary());

    FlyUI::end(); // end horizontal stack
    FlyUI::end(); // end section
});

/**
 * Demo: Components - Button Groups
 * 
 * ----------------------------------------------------------------------------
 */
UIDemo("Components - Button Groups", function(RenderContext $context, RenderTarget $target, FlyUiDemoState $state) : void 
{
    static $selectedSize = 'medium';


    FlyUI::buttonGroup(
        'Size',
        ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'],
        $selectedSize,
        function(string $option) {
            echo "Selected size: " . $option . "\n";
        }
    );

    FlyUI::spaceY(20);


    FlyUI::beginSection('Current Selections');
    FlyUI::text("Size: " . $selectedSize);
    FlyUI::end(); // end section
});

/**
 * Demo: Components - Checkboxes
 * 
 * ----------------------------------------------------------------------------
 */
UIDemo("Components - Checkboxes", function(RenderContext $context, RenderTarget $target, FlyUiDemoState $state) : void 
{
    static $enableNotifications = true;
    static $darkMode = false;
    static $autoSave = true;
    static $showGrid = false;
    static $enableSounds = true;

    FlyUI::beginSection('Basic Checkboxes');
    FlyUI::beginLayout()
        ->flow(FUILayoutFlow::vertical)
        ->spacing(5);

    FlyUI::checkbox('Enable Notifications', $enableNotifications, function(bool $checked) {
        echo "Notifications " . ($checked ? "enabled" : "disabled") . "\n";
    });

    FlyUI::checkbox('Dark Mode', $darkMode, function(bool $checked) {
        echo "Dark mode " . ($checked ? "enabled" : "disabled") . "\n";
    });

    FlyUI::checkbox('Auto Save', $autoSave, function(bool $checked) {
        echo "Auto save " . ($checked ? "enabled" : "disabled") . "\n";
    });

    FlyUI::end(); // end layout
    FlyUI::end(); // end section

    FlyUI::spaceY(20);

    FlyUI::beginSection('Settings Panel');
    FlyUI::beginLayout()
        ->flow(FUILayoutFlow::vertical)
        ->spacing(5);

    FlyUI::text('Display Options:', VGColor::rgb(0.3, 0.3, 0.3))->fontSize(14);
    FlyUI::spaceY(5);
    
    FlyUI::checkbox('Show Grid', $showGrid, function(bool $checked) {
        echo "Grid display " . ($checked ? "enabled" : "disabled") . "\n";
    });

    FlyUI::spaceY(10);

    FlyUI::text('Audio Options:', VGColor::rgb(0.3, 0.3, 0.3))->fontSize(14);
    FlyUI::spaceY(5);

    FlyUI::checkbox('Enable Sounds', $enableSounds, function(bool $checked) {
        echo "Sounds " . ($checked ? "enabled" : "disabled") . "\n";
    });

    FlyUI::end(); // end layout
    FlyUI::end(); // end section

    FlyUI::spaceY(20);

    FlyUI::beginSection('Current State');
    FlyUI::beginLayout()
        ->flow(FUILayoutFlow::vertical)
        ->spacing(3);
    
    FlyUI::text("Notifications: " . ($enableNotifications ? "ON" : "OFF"));
    FlyUI::text("Dark Mode: " . ($darkMode ? "ON" : "OFF"));
    FlyUI::text("Auto Save: " . ($autoSave ? "ON" : "OFF"));
    FlyUI::text("Show Grid: " . ($showGrid ? "ON" : "OFF")); 
    FlyUI::text("Enable Sounds: " . ($enableSounds ? "ON" : "OFF"));
    
    FlyUI::end(); // end layout
    FlyUI::end(); // end section
});

/**
 * Main Entry Point
 * 
 * ----------------------------------------------------------------------------
 */
$quickstart = new Quickstart(function(QuickstartOptions $app) use(&$state)
{
    // preselect the first demo
    $state->currentDemo = array_key_first($state->uiDemoFunctions);

    $app->ready = function(QuickstartApp $app) {
        // FlyUI::enablePerformanceTracing(true);
    };

    $app->draw = function(QuickstartApp $app, RenderContext $context, RenderTarget $target) use(&$state) 
    {
        $target->framebuffer()->clear();

        // main sidebar/content layout
        FlyUI::beginLayout(new Vec4(25))
            ->flow(FUILayoutFlow::horizontal)
            ->backgroundColor(VGColor::white())
            ->verticalFill()
            ->horizontalFill()
            ->spacing(20);

            // sidebar
            FlyUI::beginLayout(new Vec4(10))
                ->flow(FUILayoutFlow::vertical)
                ->backgroundColor(VGColor::rgb(0.9, 0.9, 0.9), 5.0)
                ->verticalFill()
                ->fixedWidth(250)
                ->spacing(10);

                // sidebar buttons for each demo
                foreach ($state->uiDemoFunctions as $demoName => $demoFunc) {
                    FlyUI::button($demoName, function() use($demoName, &$state) {
                        $state->currentDemo = $demoName;
                    })->fullWidth = true;
                }

            FlyUI::end();

            // content area
            FlyUI::beginLayout(new Vec4(10))
                ->flow(FUILayoutFlow::vertical)
                ->backgroundColor(VGColor::rgb(0.95, 0.95, 0.95), 5.0)
                ->horizontalFill()
                ->verticalFill();
                
                // render the currently selected demo
                if (isset($state->uiDemoFunctions[$state->currentDemo])) {
                    $demoFunc = $state->uiDemoFunctions[$state->currentDemo];
                    $demoFunc($context, $target, $state);
                }

            FlyUI::end();


        FlyUI::end();

    };
});

$quickstart->run();
