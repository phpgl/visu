<?php 

namespace VISU\Tests;

use VISU\Graphics\GLState;
use VISU\OS\Window;

abstract class GLContextTestCase extends \PHPUnit\Framework\TestCase
{
    protected static bool $glfwInitialized = false;

    protected static GLState $glstate;

    protected static ?Window $globalWindow = null;

    protected const TEST_VIEW_WIDTH = 480;
    protected const TEST_VIEW_HEIGHT = 360;

    public function setUp() : void
    {
        if (!self::$glfwInitialized) 
        {
            if (!glfwInit()) {
                throw new \Exception("Could not initalize glfw...");
            }

            self::$glfwInitialized = true;
            self::$glstate = new GLState;
        }
    }

    public function createWindow() : Window
    {
        if (self::$globalWindow === null) {
            $window = new Window("PHPUnit Offscreen", self::TEST_VIEW_WIDTH, self::TEST_VIEW_HEIGHT);
            $window->initailize(self::$glstate);

            self::$globalWindow = $window;
        }

        return self::$globalWindow;
    }
}