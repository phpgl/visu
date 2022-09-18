<?php 

namespace VISU\OS;

use GL\Math\Vec2;
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
     * @param GLState $state A state object representing the current global OpenGL state
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
     * @param GLState $state The global GL state
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
     * 
     * @return bool True if the window should close, false otherwise
     */
    public function shouldClose() : bool
    {
        return (bool) glfwWindowShouldClose($this->requiresInitialization());
    }

    /**
     * Sets the window should close flag
     * 
     * @param bool $value true to request closing, false to cancel a request
     */
    public function setShouldClose(bool $value) : void
    {
        glfwSetWindowShouldClose($this->requiresInitialization(), (int) $value);
    }

    /**
     * Get the window title
     * 
     * @return string current window title
     */
    public function getTitle() : string
    {
        return $this->title;
    }

    /**
     * Updates the window title
     * 
     * @param string $title The new window title
     */
    public function setTitle(string $title) : void
    {
        $this->title = $title;
        glfwSetWindowTitle($this->requiresInitialization(), $title);
    }

    /**
     * Get the window width
     * 
     * @return int The window width in screen coordinates
     */
    public function getWidth() : int
    {
        return $this->width;
    }

    /**
     * Get the window height
     * 
     * @return int the windows height in screen coordinates
     */
    public function getHeight() : int
    {
        return $this->height;
    }

    /**
     * Pulls the window size from the window resource
     * If you allow the user to resize the window you should call this method
     * to update the internal size variables.
     */
    public function pullSize() : void
    {
        glfwGetWindowSize($this->requiresInitialization(), $this->width, $this->height);
    }

    /**
     * Get the window size in screen coordinates as a Vec2
     * 
     * @return Vec2 The window size as a Vec2
     */
    public function getSizeVec() : Vec2
    {
        return new Vec2($this->width, $this->height);
    }

    /**
     * Set the window size in screen coordinates
     * 
     * @param int $width The new window width in screen coordinates
     * @param int $height The new window height in screen coordinates
     */
    public function setSize(int $width, int $height) : void
    {
        $this->width = $width;
        $this->height = $height;
        glfwSetWindowSize($this->requiresInitialization(), $width, $height);
    }
    
    /**
     * Get the window position in screen coordinates as a Vec2
     * 
     * @return Vec2 a vector containing the window position
     */
    public function getPositionVec() : Vec2
    {
        $x = 0;
        $y = 0;
        glfwGetWindowPos($this->requiresInitialization(), $x, $y);
        return new Vec2($x, $y);
    }    
} 