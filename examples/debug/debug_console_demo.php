<?php

use GL\Math\Vec3;
use GL\Math\Vec4;
use GL\VectorGraphics\VGColor;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\RenderTarget;
use VISU\Quickstart;
use VISU\Quickstart\QuickstartApp;
use VISU\Quickstart\QuickstartOptions;
use VISU\FlyUI\FlyUI;
use VISU\Runtime\DebugConsole;
use VISU\Signals\Runtime\ConsoleCommandSignal;

$container = require __DIR__ . '/../bootstrap.php';

// state for our demo
$demoState = [
    'message' => 'Welcome to the Debug Console Demo!',
    'counter' => 0,
    'color' => new Vec3(1.0, 1.0, 1.0),
];

/**
 * Custom QuickstartApp with Debug Console Integration
 */
class DebugConsoleQuickstartApp extends QuickstartApp
{
    private ?DebugConsole $debugConsole = null;

    public function setDebugConsole(DebugConsole $console): void
    {
        $this->debugConsole = $console;
    }

    public function setupDrawAfter(RenderContext $context, RenderTargetResource $renderTarget): void
    {
        parent::setupDrawAfter($context, $renderTarget);
        
        // attach the debug console rendering pass after all other rendering
        if ($this->debugConsole) {
            $this->debugConsole->attachPass($context->pipeline, $context->resources, $renderTarget);
        }
    }
}

/**
 * Main Entry Point
 * 
 * ----------------------------------------------------------------------------
 */
$quickstart = new Quickstart(function(QuickstartOptions $app) use(&$demoState)
{
    $app->windowTitle = 'VISU Debug Console Demo';
    $app->appClass = DebugConsoleQuickstartApp::class;

    $app->ready = function(DebugConsoleQuickstartApp $app) use(&$demoState) {
        // create and configure the debug console
        $debugConsole = new DebugConsole(
            $app->gl,
            $app->input,
            $app->dispatcher
        );

        // load a base font
        $app->vg->createFont('inter-regular', VISU_PATH_FRAMEWORK_RESOURCES_FONT . '/inter/Inter-Regular.ttf');
        $app->vg->fontFace('inter-regular');

        // store the console in the container for easy access
        $app->container->set('debugConsole', $debugConsole);
        
        // set the debug console on our custom app
        $app->setDebugConsole($debugConsole);

        // register console command handlers
        $app->dispatcher->register(DebugConsole::EVENT_CONSOLE_COMMAND, function(ConsoleCommandSignal $signal) use(&$demoState, $app) {
            
            // handle 'help' command
            if ($signal->isAction('help')) {
                $signal->console->writeLine('Available commands:');
                $signal->console->writeLine('  help - Show this help message');
                $signal->console->writeLine('  clear - Clear console history');
                $signal->console->writeLine('  echo <message> - Echo a message');
                $signal->console->writeLine('  set message <text> - Set display message');
                $signal->console->writeLine('  set color <r> <g> <b> - Set text color (0-1)');
                $signal->console->writeLine('  counter - Show current counter value');
                $signal->console->writeLine('  counter inc - Increment counter');
                $signal->console->writeLine('  counter dec - Decrement counter');
                $signal->console->writeLine('  counter reset - Reset counter to 0');
                return;
            }

            // handle 'clear' command
            if ($signal->isAction('clear')) {
                $signal->console->clearHistory();
                $signal->console->writeLine('Console cleared.');
                return;
            }

            // handle 'echo' command
            if ($signal->isAction('echo')) {
                $message = implode(' ', array_slice($signal->commandParts, 1));
                $signal->console->writeLine('Echo: ' . $message);
                return;
            }

            // handle 'set' commands
            if ($signal->isAction('set')) {
                if (count($signal->commandParts) < 2) {
                    $signal->console->writeLine('Usage: set <property> <value>');
                    return;
                }

                $property = $signal->commandParts[1];
                
                if ($property === 'message') {
                    $demoState['message'] = implode(' ', array_slice($signal->commandParts, 2));
                    $signal->console->writeLine('Message set to: ' . $demoState['message']);
                } elseif ($property === 'color') {
                    if (count($signal->commandParts) < 5) {
                        $signal->console->writeLine('Usage: set color <r> <g> <b>');
                        return;
                    }
                    $r = (float)$signal->commandParts[2];
                    $g = (float)$signal->commandParts[3];
                    $b = (float)$signal->commandParts[4];
                    $demoState['color'] = new Vec3($r, $g, $b);
                    $signal->console->writeLine("Color set to RGB($r, $g, $b)");
                } else {
                    $signal->console->writeLine("Unknown property: $property");
                }
                return;
            }

            // handle 'counter' commands
            if ($signal->isAction('counter')) {
                if (count($signal->commandParts) === 1) {
                    $signal->console->writeLine('Counter value: ' . $demoState['counter']);
                    return;
                }

                $action = $signal->commandParts[1];
                if ($action === 'inc') {
                    $demoState['counter']++;
                    $signal->console->writeLine('Counter incremented to: ' . $demoState['counter']);
                } elseif ($action === 'dec') {
                    $demoState['counter']--;
                    $signal->console->writeLine('Counter decremented to: ' . $demoState['counter']);
                } elseif ($action === 'reset') {
                    $demoState['counter'] = 0;
                    $signal->console->writeLine('Counter reset to 0');
                } else {
                    $signal->console->writeLine("Unknown counter action: $action");
                }
                return;
            }

            // unknown command
            $signal->console->writeLine("Unknown command: {$signal->commandParts[0]}. Type 'help' for available commands.");
        });

        // write initial welcome message to console
        $debugConsole->writeLine('Debug Console Demo initialized!');
        $debugConsole->writeLine('Press Ctrl+C to toggle the console.');
        $debugConsole->writeLine('Type "help" for available commands.');
    };

    $app->draw = function(QuickstartApp $app, RenderContext $context, RenderTarget $target) use(&$demoState) 
    {
        $target->framebuffer()->clear();
        
        // main layout with padding and centered content
        FlyUI::beginLayout(new Vec4(50))
            ->backgroundColor(VGColor::rgb(0.05, 0.05, 0.1))
            ->verticalFill()
            ->horizontalFill()
            ->spacing(20);
        
        // title
        FlyUI::text('VISU Debug Console Demo', VGColor::white())
            ->fontSize(32);
        
        // instructions
        FlyUI::text('Press Ctrl+C to toggle the debug console', VGColor::rgb(0.8, 0.8, 0.8))
            ->fontSize(16);
        
        FlyUI::spaceY(30);
        
        // current message with custom color
        $color = $demoState['color'];
        FlyUI::text($demoState['message'], VGColor::rgb($color->x, $color->y, $color->z))
            ->fontSize(24);
        
        FlyUI::spaceY(20);
        
        // counter display
        FlyUI::text('Counter: ' . $demoState['counter'], VGColor::rgb(0.9, 0.9, 0.1))
            ->fontSize(20);
        
        FlyUI::spaceY(30);
        
        // entities section (if any exist)
        if (!empty($demoState['entities'])) {
            FlyUI::beginSection('Entities');
            foreach ($demoState['entities'] as $entity) {
                FlyUI::text($entity['name'] . ' at (' . round($entity['position']->x) . ', ' . round($entity['position']->y) . ')', VGColor::rgb(0.2, 0.8, 0.4))
                    ->fontSize(14);
            }
            FlyUI::end(); // end entities section
            
            FlyUI::spaceY(20);
        }
        
        // commands reference
        FlyUI::beginSection('Console Commands');
        FlyUI::text('Available commands: help, echo, set, counter, entities', VGColor::rgb(0.6, 0.6, 0.6))
            ->fontSize(12);
        FlyUI::end(); // end commands section
        
        FlyUI::end(); // end main layout
    };
});

$quickstart->run();