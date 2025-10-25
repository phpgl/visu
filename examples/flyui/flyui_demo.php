<?php

use GL\Math\Vec2;
use GL\Math\Vec4;
use GL\VectorGraphics\VGColor;
use VISU\ECS\EntityRegisty;
use VISU\FlyUI\FlyUI;
use VISU\FlyUI\FUIButtonGroup;
use VISU\FlyUI\FUILayoutFlow;
use VISU\FlyUI\FUILayoutSizing;
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
    public FUILayoutSizing $stackSizing = FUILayoutSizing::fill;
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
        ->spacing(5);

    for ($i = 0; $i < 10; $i++) {
        $color = VGColor::white()->darken(($i / 10));

        FlyUI::beginLayout(new Vec4(10))
            ->verticalSizing($state->stackSizing)
            ->backgroundColor($color, 3.0);
        

        // find a text color that contrasts well with the background
        FlyUI::text("This is box #" . ($i + 1), $color->contrast())
            ->fontSize(12);

        FlyUI::end();
    }

    FlyUI::end(); // end left content area

    // right settings area
    FlyUI::beginLayout()
        ->verticalFill()
        ->fixedWidth(200)
        ->spacing(5);

    FlyUI::text("Settings")
        ->bold()
        ->fontSize(16);

    FlyUI::text("Stack Flow:")->fontSize(14);
    FlyUI::button("Vertical", function() use($state) {
        $state->stackFlow = FUILayoutFlow::vertical;
    });

    FlyUI::button("Horizontal", function() use($state) {
        $state->stackFlow = FUILayoutFlow::horizontal;
    });

    // FlyUI::buttonGroup(['fill' => 'Fill', 'fit' => 'Fit'], $state->stackSizing->name, function(string $option) use(&$state) {
    //     var_dump($option);
    //     if ($option === 'fill') {
    //         $state->stackSizing = FUILayoutSizing::fill;
    //     } else {
    //         $state->stackSizing = FUILayoutSizing::fit;
    //     }
    // });

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
    });

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
    static $selectedStyle = 'primary';
    static $selectedLayout = 'horizontal';

    FlyUI::beginSection('Button Group with Reference');
    FlyUI::beginHorizontalStack();

    FlyUI::buttonGroup(
        'size-selector',
        ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'],
        $selectedSize,
        function(string $option) {
            echo "Selected size: " . $option . "\n";
        }
    );

    FlyUI::end(); // end horizontal stack
    FlyUI::end(); // end section

    FlyUI::beginSection('Another Button Group (Custom Hover)');
    FlyUI::beginHorizontalStack();

    FlyUI::buttonGroup(
        'style-selector',
        ['primary' => 'Primary', 'secondary' => 'Secondary', 'accent' => 'Accent'],
        $selectedStyle,
        function(string $option) {
            echo "Selected style: " . $option . "\n";
        }
    )->setHoverOverlayColor(new VGColor(0.2, 0.4, 0.8, 0.15)); // Light blue hover

    FlyUI::end(); // end horizontal stack
    FlyUI::end(); // end section

    FlyUI::beginSection('Layout Selection (Slow Animation)');
    FlyUI::beginHorizontalStack();

    FlyUI::buttonGroup(
        'layout-selector',
        ['horizontal' => 'Horizontal', 'vertical' => 'Vertical', 'grid' => 'Grid'],
        $selectedLayout,
        function(string $option) {
            echo "Selected layout: " . $option . "\n";
        }
    )->setAnimationSpeed(3.0); // Slower animation

    FlyUI::end(); // end horizontal stack
    FlyUI::end(); // end section

    FlyUI::beginSection('Current Selections');
    FlyUI::text("Size: " . $selectedSize);
    FlyUI::text("Style: " . $selectedStyle);
    FlyUI::text("Layout: " . $selectedLayout);
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
