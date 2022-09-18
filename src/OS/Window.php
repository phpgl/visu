<?php 

namespace VISU\OS;

use GLFWmonitor;
use GLFWwindow;
use VISU\Graphics\GLState;

class Window
{
    /**
     * Window title
     */
    private string $title;

    /**
     * Window width
     */
    private int $width;

    /**
     * Window height
     */
    private int $height;

    /**
     * GLFW Window handle
     */
    protected ?GLFWwindow $handle = null;

    /**
     * Current windows hints
     */
    public readonly WindowHints $hints;

    /**
     * Window constructor
     * The constructor does not create thw window resource yet, use `initailize` for that.
     * 
     * Make sure GLFW is initialized before creating a window.
     * 
     * @param string $title The window title displayed in the title bar
     * @param int $width The window width in screen coordinates
     * @param int $height The window height in screen coordinates
     * @param WindowHints $hints The window hints used during initalization
     */
    public function __construct(
        string $title, 
        int $width = 1280, 
        int $height = 720, 
        ?WindowHints $hints = null, 
    )
    {
        $this->title = $title;
        $this->width = $width;
        $this->height = $height;
        $this->hints = $hints ?? new WindowHints();
    }

    /**
     * Throws an exception if the window is not initialized
     */
    private function requiresInitialization() : GLFWwindow
    {
        if ($this->handle === null) {
            throw new UninitializedWindowException("Window is not initialized, call `initialize` first");
        }
        
        return $this->handle;
    }

    /**
     * This will actually craete the window resource and launch it
     * 
     * @param null|GLFWmonitor $initalMonitor If not null, the window will be created in fullscreen mode for the specified monitor
     * @return void 
     */
    public function initailize(GLState $state, ?GLFWmonitor $initalMonitor = null) : void
    {
        if ($this->handle !== null) {
            throw new WindowException("Window already initialized, cannot initialize again.");
        }

        // apply the hints
        $this->hints->apply();

        // create the window
        $this->handle = glfwCreateWindow($this->width, $this->height, $this->title, $initalMonitor, null);

        // activate the window
        $this->activate($state);
    }

    /**
     * Active the windows GL context and make it the current context
     * This internally calls `glfwMakeContextCurrent`
     * 
     * @return void
     */
    public function activate(GLState $state) : void
    {
        $handle = $this->requiresInitialization();

        // only activate if the window is not already active
        if ($state->window !== $this) {
            glfwMakeContextCurrent($handle);
            $state->window = $this;
        }
    }

    /**
     * Window destructor
     */
    public function __destruct()
    {
        if ($this->handle !== null) {
            glfwDestroyWindow($this->handle);
        }
    }

    /**
     * Does the window request closing?
     */
    public function shouldClose() : bool
    {
        return (bool) glfwWindowShouldClose($this->requiresInitialization());
    }

    /**
     * Sets the window should close flag
     */
    public function setShouldClose(bool $value) : void
    {
        glfwSetWindowShouldClose($this->requiresInitialization(), (int) $value);
    }
}  