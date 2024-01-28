<?php

namespace VISU;

use ClanCats\Container\Container;
use VISU\Exception\VISUException;
use VISU\Quickstart\QuickstartApp;
use VISU\Quickstart\QuickstartOptions;
use VISU\Runtime\GameLoop;

/**
 * Quickstart prepares a basic and simple VISU runtime environemnt to give you,
 * you guessed it, a quick start. It is not intended to be used in large and complex apps
 * but rather for quick prototyping, testing and learning.
 * 
 * VISU Quickstart will cut a lot of corners and make a lot of assumptions for you.
 * 
 * Additionally quickstart can expose global aliases for the runtime alla Laravel's Facades.
 * This is purely optional and again just for quick prototyping, testing and learning.
 */
class Quickstart
{
    /**
     * Instance of the app we are building and running.
     */
    private QuickstartApp $app;

    /**
     * Create a new Quickstart instance.
     * 
     * @param callable(QuickstartOptions): void $appBuilder
     */
    public function __construct(callable $appBuilder)
    {
        // check that the glfw extension is loaded
        if (!extension_loaded('glfw')) {
            throw new VISUException("The glfw extension is not loaded, please check your installation.");
        }

        if (!glfwInit()) {
            throw new VISUException("Could not initalize glfw, please report this issue to php-glfw.");
        }

        // create an app container to store all our services
        $container = new Container();

        // create the quickstart app options and let the user configure it
        $options = new QuickstartOptions();
        $appBuilder($options);

        // construct the quickstart app
        $className = $options->appClass;
        
        // sanity check that the class name is a subclass of QuickstartApp
        if ($className !== QuickstartApp::class && !is_subclass_of($className, QuickstartApp::class)) {
            throw new VISUException("The app class '{$className}' is not a subclass of QuickstartApp, this is required for the quickstart bootstrap.");
        }

        $this->app = new $className($container, $options);

        // register a game loop in the container
        $container->set('loop', new GameLoop($this->app, $options->gameLoopTickRate, $options->gameLoopMaxUpdatesPerFrame));
    }

    /**
     * Run the quickstart app.
     */
    public function run() : void
    {
        // run ready callback
        $this->app->ready();

        // start the game loop
        $this->app->container->getTyped(GameLoop::class, 'loop')->start();
    }
}