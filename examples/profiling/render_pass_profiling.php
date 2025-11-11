<?php

use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\RenderTarget;
use VISU\Quickstart;
use VISU\Quickstart\QuickstartApp;
use VISU\Quickstart\QuickstartOptions;

$container = require __DIR__ . '/../bootstrap.php';

/**
 * Main Entry Point
 * 
 * ----------------------------------------------------------------------------
 */
$quickstart = new Quickstart(function(QuickstartOptions $app) use(&$state)
{
    $app->ready = function(QuickstartApp $app) {
        $app->loadCompatGPUProfiler();
    };

    $app->draw = function(QuickstartApp $app, RenderContext $context, RenderTarget $target) use(&$state) 
    {
        $target->framebuffer()->clear();
        
        // sleep for 2ms to simulate work
        usleep(2000);
    };
});

$quickstart->run();
