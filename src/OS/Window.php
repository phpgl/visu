<?php 

namespace VISU\OS;

use GL\Math\Vec2;

use GLFWmonitor;
use GLFWwindow;

use VISU\Graphics\GLState;
use VISU\OS\Exception\{WindowException, UninitializedWindowException};

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
     * Current window event handler
     */
    private WindowEventHandlerInterface $eventHandler;

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
            $this->handle = null;
        }
    }

    /**
     * Returns the plain GLFW window handle
     * 
     * @return GLFWwindow 
     * @throws UninitializedWindowException 
     */
    public function getGLFWHandle() : GLFWwindow
    {
        return $this->requiresInitialization();
    }

    /**
     * Sets the windows event handler and registers it with GLFW
     * 
     * @param WindowEventHandlerInterface $handler 
     * @return void 
     */
    public function setEventHandler(WindowEventHandlerInterface $handler) : void
    {
        $glfwWindow = $this->requiresInitialization();
        $this->eventHandler = $handler;

        // register all event handlers
        glfwSetKeyCallback($glfwWindow, [$this, 'triggerWindowKeyEvent']);
        glfwSetCharCallback($glfwWindow, [$this, 'triggerWindowCharEvent']);
        glfwSetCharModsCallback($glfwWindow, [$this, 'triggerWindowCharModsEvent']);
        glfwSetMouseButtonCallback($glfwWindow, [$this, 'triggerWindowMouseButtonEvent']);
        glfwSetCursorPosCallback($glfwWindow, [$this, 'triggerWindowCursorPosEvent']);
        glfwSetCursorEnterCallback($glfwWindow, [$this, 'triggerWindowCursorEnterEvent']);
        glfwSetScrollCallback($glfwWindow, [$this, 'triggerWindowScrollEvent']);
        glfwSetDropCallback($glfwWindow, [$this, 'triggerWindowDropEvent']);

        // register window events
        glfwSetWindowSizeCallback($glfwWindow, function($width, $height) {
            $this->width = $width;
            $this->height = $height;
        });
    }

    /**
     * Triggers a window key event.
     * This methods assumes that `setEventHandler` has been called before. 
     * You can call this method yourself to simulate a key event.
     */
    public function triggerWindowKeyEvent(int $key, int $scancode, int $action, int $mods) : void
    {
        $this->eventHandler->handleWindowKey($this, $key, $scancode, $action, $mods);
    }

    /**
     * Triggers a window char event.
     * This methods assumes that `setEventHandler` has been called before. 
     * You can call this method yourself to simulate a char event.
     */
    public function triggerWindowCharEvent(int $codepoint) : void
    {
        $this->eventHandler->handleWindowChar($this, $codepoint);
    }

    /**
     * Triggers a window char mods event.
     * This methods assumes that `setEventHandler` has been called before. 
     * You can call this method yourself to simulate a char mods event.
     */
    public function triggerWindowCharModsEvent(int $codepoint, int $mods) : void
    {
        $this->eventHandler->handleWindowCharMods($this, $codepoint, $mods);
    }

    /**
     * Triggers a window mouse button event.
     * This methods assumes that `setEventHandler` has been called before. 
     * You can call this method yourself to simulate a mouse button event.
     */
    public function triggerWindowMouseButtonEvent(int $button, int $action, int $mods) : void
    {
        $this->eventHandler->handleWindowMouseButton($this, $button, $action, $mods);
    }

    /**
     * Triggers a window cursor position event.
     * This methods assumes that `setEventHandler` has been called before. 
     * You can call this method yourself to simulate a cursor position event.
     */
    public function triggerWindowCursorPosEvent(float $xpos, float $ypos) : void
    {
        $this->eventHandler->handleWindowCursorPos($this, $xpos, $ypos);
    }

    /**
     * Triggers a window cursor enter event.
     * This methods assumes that `setEventHandler` has been called before. 
     * You can call this method yourself to simulate a cursor enter event.
     */
    public function triggerWindowCursorEnterEvent(int $entered) : void
    {
        $this->eventHandler->handleWindowCursorEnter($this, $entered);
    }

    /**
     * Triggers a window scroll event.
     * This methods assumes that `setEventHandler` has been called before. 
     * You can call this method yourself to simulate a scroll event.
     */
    public function triggerWindowScrollEvent(float $xoffset, float $yoffset) : void
    {
        $this->eventHandler->handleWindowScroll($this, $xoffset, $yoffset);
    }

    /**
     * Triggers a window drop event.
     * This methods assumes that `setEventHandler` has been called before. 
     * You can call this method yourself to simulate a drop event.
     * 
     * @param array<string>       $paths
     */
    public function triggerWindowDropEvent(array $paths) : void
    {
        $this->eventHandler->handleWindowDrop($this, $paths);
    }

    /**
     * Poll the queued window events and run the callbacks
     */
    public function pollEvents() : void
    {
        $this->requiresInitialization();
        glfwPollEvents();
    }

    /**
     * Swap the windows framebuffer
     * 
     * This will swap the front and back buffer of the window
     * If not explicitly disabled we have a double buffered window by default
     * which requires this call to be made after rendering
     */
    public function swapBuffers() : void
    {
        glfwSwapBuffers($this->requiresInitialization());
    }

    /**
     * Returns a window attribute using a GLFW constant.
     * 
     * @param int $attribute The attribute to query
     * @return int The attribute value
     */
    public function getAttribute(int $attribute) : int
    {
        return glfwGetWindowAttrib($this->requiresInitialization(), $attribute);
    }

    /**
     * Sets a window attribute using a GLFW constant. 
     * VISU validates the options and throws an exception if the attribute is not changeable.
     * 
     * @param int $attribute 
     * @param int $value 
     * @return void 
     */
    public function setAttribute(int $attribute, int $value) : void
    {
        if (!in_array($attribute, [GLFW_DECORATED, GLFW_FLOATING, GLFW_RESIZABLE, GLFW_VISIBLE, GLFW_AUTO_ICONIFY, GLFW_FOCUS_ON_SHOW])) {
            throw new WindowException("Attribute $attribute is not modifiable with an existing window.");
        }

        glfwSetWindowAttrib($this->requiresInitialization(), $attribute, $value);
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

    /**
     * Set the window position in screen coordinates
     * 
     * @param int $x The new window x position in screen coordinates
     * @param int $y The new window y position in screen coordinates
     */
    public function setPosition(int $x, int $y) : void
    {
        glfwSetWindowPos($this->requiresInitialization(), $x, $y);
    }

    /**
     * Get the window framebuffer size in pixels as a Vec2
     * 
     * @return Vec2 a vector containing the window framebuffer size
     */
    public function getFramebufferSizeVec() : Vec2
    {
        $x = 0;
        $y = 0;
        glfwGetFramebufferSize($this->requiresInitialization(), $x, $y);
        return new Vec2($x, $y);
    }

    /**
     * Get the window framebuffer width in pixels
     * 
     * @return int The window framebuffer width in pixels
     */
    public function getFramebufferWidth() : int
    {
        $x = 0;
        $y = 0;
        glfwGetFramebufferSize($this->requiresInitialization(), $x, $y);
        return $x;
    }

    /**
     * Get the window framebuffer height in pixels
     * 
     * @return int The window framebuffer height in pixels
     */
    public function getFramebufferHeight() : int
    {
        $x = 0;
        $y = 0;
        glfwGetFramebufferSize($this->requiresInitialization(), $x, $y);
        return $y;
    }

    /**
     * Set the windows size limits in screen coordinates
     * 
     * @param int $minWidth The minimum window width in screen coordinates
     * @param int $minHeight The minimum window height in screen coordinates
     * @param int $maxWidth The maximum window width in screen coordinates
     * @param int $maxHeight The maximum window height in screen coordinates
     */
    public function setSizeLimits(int $minWidth, int $minHeight, int $maxWidth, int $maxHeight) : void
    {
        glfwSetWindowSizeLimits($this->requiresInitialization(), $minWidth, $minHeight, $maxWidth, $maxHeight);
    }

    /**
     * Set the windows aspect ratio
     * 
     * @param int $numerator The aspect ratio numerator
     * @param int $denominator The aspect ratio denominator
     */
    public function setAspectRatio(int $numerator, int $denominator) : void
    {
        glfwSetWindowAspectRatio($this->requiresInitialization(), $numerator, $denominator);
    }

    /**
     * Maximizes the window
     */
    public function maximize() : void
    {
        glfwMaximizeWindow($this->requiresInitialization());
    }

    /**
     * Returns boolean if the window is maximized
     * 
     * @return bool True if the window is maximized, false otherwise
     * 
     */
    public function isMaximized() : bool
    {
        return (bool) glfwGetWindowAttrib($this->requiresInitialization(), GLFW_MAXIMIZED);
    } 

    /**
     * Iconifies the window
     */
    public function iconify() : void
    {
        glfwIconifyWindow($this->requiresInitialization());
    }

    /**
     * Returns boolean if the window is iconified
     * 
     * @return bool True if the window is iconified, false otherwise
     * 
     */
    public function isIconified() : bool
    {
        return (bool) glfwGetWindowAttrib($this->requiresInitialization(), GLFW_ICONIFIED);
    }

    /**
     * Restores the window
     * This can be called to undo a maximize or iconify call
     */
    public function restore() : void
    {
        glfwRestoreWindow($this->requiresInitialization());
    }

    /**
     * Show the window
     * This will make the window visible again after a hide call. Dont confuse this with initialize.
     */
    public function show() : void
    {
        glfwShowWindow($this->requiresInitialization());
    }

    /**
     * Hide the window
     * This will make the window invisible but not destroy it
     */
    public function hide() : void
    {
        glfwHideWindow($this->requiresInitialization());
    }

    /**
     * Returns boolean if the window is visible (show/hide state)
     *  
     * @return bool True if the window is visible
     */
    public function isVisible() : bool
    {
        return glfwGetWindowAttrib($this->requiresInitialization(), GLFW_VISIBLE) === GLFW_TRUE;
    }

    /**
     * Give the window input focus
     * Call this to really annoy the user.
     */
    public function focus() : void
    {
        glfwFocusWindow($this->requiresInitialization());
    }

    /**
     * Returns boolean if the window has input focus
     * 
     * @return bool True if the window has input focus
     */
    public function hasFocus() : bool
    {
        return glfwGetWindowAttrib($this->requiresInitialization(), GLFW_FOCUSED) === GLFW_TRUE;
    }

    /**
     * Request attention, window attention request
     * This will make the window title bar flash depending on the OS
     */
    public function requestAttention() : void
    {
        glfwRequestWindowAttention($this->requiresInitialization());
    }
} 