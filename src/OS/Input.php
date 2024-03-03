<?php 

namespace VISU\OS;

use GLFWwindow;

use GL\Math\Vec2;

use VISU\Signal\DispatcherInterface;
use VISU\Signals\Input\{
    CharModSignal,
    CharSignal,
    CursorEnterSignal,
    CursorPosSignal,
    DropSignal,
    KeySignal,
    MouseButtonSignal,
    MouseClickSignal,
    ScrollSignal
};

/**
 * This class is responsible for handling window events, and includes a bunch of
 * helpers and utility methods to easly handle user input.
 */
class Input implements WindowEventHandlerInterface
{
    /**
     * We warp the GLFW state constants to make the syntax a bit more eye pleasing.
     */
    // states
    public const PRESS = GLFW_PRESS;
    public const RELEASE = GLFW_RELEASE;
    public const REPEAT = GLFW_REPEAT;

    /**
     * Current input context is just represented by a string.
     * The input context can be used as mutex like flag so that 
     * different systems can use the input class without interfering with each other.
     */
    private ?string $currentInputContext = null;

    /**
     * GLFW window instance.
     * required to fetch key states. We copy a reference to the raw window
     * so we don't always have to use `getGLFWHandle` everytime we want to check 
     * a windows key state.
     * 
     * @var GLFWwindow
     */
    private \GLFWwindow $glfwWindowHandle;

    /**
     * The last polled cursor position
     * 
     * @var Vec2
     */
    private Vec2 $lastCursorPosition;

    /**
     * Last left mouse button pressed down cursor position
     * 
     * This is needed to calculate the delta needed to cancel click events
     */
    private Vec2 $lastLeftMouseDownPosition;

    /**
     * Last left mouse button released cursor position
     * 
     * This is needed to calculate the delta needed to cancel click events
     */
    private Vec2 $lastLeftMouseReleasePosition;

    /**
     * The maximum distance the cursor can move between a mouse down and mouse up event
     * to still trigger a mouse click event.
     */
    private float $mouseClickMaxDistanceFromStart = 10.0;

    /**
     * An array of all mouse buttons that have been pressed since the last poll
     * 
     * @var array<int, bool>
     */
    private array $mouseButtonsDidPress = [];

    /**
     * An array of all mouse buttons that have been released since the last poll
     * 
     * @var array<int, bool>
     */
    private array $mouseButtonsDidRelease = [];

    /**
     * Same as "mouseButtonsDidPress" but for per frame state instead of per poll state
     * 
     * @var array<int, bool>
     */
    private array $mouseButtonsDidPressFrame = [];

    /**
     * Same as "mouseButtonsDidRelease" but for per frame state instead of per poll state
     * 
     * @var array<int, bool>
     */
    private array $mouseButtonsDidReleaseFrame = [];


    /**
     * The event names the input class will dispatch on
     */
    const EVENT_KEY = 'input.key';
    const EVENT_MOUSE_BUTTON = 'input.mouse_button';
    const EVENT_MOUSE_CLICK = 'input.mouse_click';
    const EVENT_CURSOR = 'input.cursor';
    const EVENT_CURSOR_ENTER = 'input.cursor_enter';
    const EVENT_SCROLL = 'input.scroll';
    const EVENT_DROP = 'input.drop';
    const EVENT_CHAR = 'input.char';
    const EVENT_CHAR_MOD = 'input.char_mods';

    /**
     * Constructor for the Input class.
     * 
     * @param Window $window The window instance to handle input for.
     * @param DispatcherInterface $dispatcher The dispatcher instance events will be dispatched to.
     * 
     * @return void 
     */
    public function __construct(
        Window $window,
        private DispatcherInterface $dispatcher
    ) {
        $this->glfwWindowHandle = $window->getGLFWHandle();
        $this->lastCursorPosition = new Vec2(0.0, 0.0);
    }

    /**
     * Get the state for a given key
     * 
     * Can return one of the following values:
     * - `Input::PRESS`
     * - `Input::RELEASE`
     * - `Input::REPEAT`
     * 
     * @param int $key The key to get the state for
     * @return int The state of the key
     */
    public function getKeyState(int $key) : int
    {
        return glfwGetKey($this->glfwWindowHandle, $key);
    }

    /**
     * Returns boolean if the given key is pressed
     * 
     * Example: 
     * ```php
     * $input->isKeyPressed(Key::SPACE);
     * ```
     * 
     * @param int $key The key to check
     * @return bool True if the key is pressed, false otherwise
     */
    public function isKeyPressed(int $key) : bool
    {
        return $this->getKeyState($key) === self::PRESS;
    }

    /**
     * Returns boolean if the given key is released
     * 
     * Example:
     * ```php
     * $input->isKeyReleased(Key::SPACE);
     * ```
     * 
     * @param int $key The key to check
     * @return bool True if the key is released, false otherwise
     */
    public function isKeyReleased(int $key) : bool
    {
        return $this->getKeyState($key) === self::RELEASE;
    }

    /**
     * Returns boolean if the given key is repeated
     * 
     * Example:
     * ```php
     * $input->isKeyRepeated(Key::SPACE);
     * ```
     * 
     * @param int $key The key to check
     * @return bool True if the key is repeated, false otherwise
     */
    public function isKeyRepeated(int $key) : bool
    {
        return $this->getKeyState($key) === self::REPEAT;
    }

    /**
     * Get the state for a given mouse button
     * 
     * Can return one of the following values:
     * - `Input::PRESS`
     * - `Input::RELEASE`
     * 
     * @param int $button The mouse button to get the state for
     * @return int The state of the mouse button
     */
    public function getMouseButtonState(int $button) : int
    {
        return glfwGetMouseButton($this->glfwWindowHandle, $button);
    }

    /**
     * Returns boolean if the given mouse button is pressed
     * 
     * Example: 
     * ```php
     * $input->isMouseButtonPressed(MouseButton::LEFT);
     * ```
     * 
     * @param int $button The mouse button to check
     * @return bool True if the mouse button is pressed, false otherwise
     */
    public function isMouseButtonPressed(int $button) : bool
    {
        return $this->getMouseButtonState($button) === self::PRESS;
    }

    /**
     * Returns boolean if the given mouse button is released
     * 
     * Example:
     * ```php
     * $input->isMouseButtonReleased(MouseButton::LEFT);
     * ```
     * 
     * @param int $button The mouse button to check
     * @return bool True if the mouse button is released, false otherwise
     */
    public function isMouseButtonReleased(int $button) : bool
    {
        return $this->getMouseButtonState($button) === self::RELEASE;
    }

    /**
     * Returns boolean if the given mouse button was pressed since the last poll
     * 
     * Example:
     * ```php
     * $input->hasMouseButtonBeenPressed(MouseButton::LEFT);
     * ```
     * 
     * @param int $button The mouse button to check
     * @return bool True if the mouse button was pressed, false otherwise
     */
    public function hasMouseButtonBeenPressed(int $button) : bool
    {
        return $this->mouseButtonsDidPress[$button] ?? false;
    }

    /**
     * Returns boolean if the given mouse button was released since the last poll
     * 
     * Example:
     * ```php
     * $input->hasMouseButtonBeenReleased(MouseButton::LEFT);
     * ```
     * 
     * @param int $button The mouse button to check
     * @return bool True if the mouse button was released, false otherwise
     */
    public function hasMouseButtonBeenReleased(int $button) : bool
    {
        return $this->mouseButtonsDidRelease[$button] ?? false;
    }

    /**
     * Returns boolean if the given mouse button was pressed since the last frame
     * 
     * Example:
     * ```php
     * $input->hasMouseButtonBeenPressedThisFrame(MouseButton::LEFT);
     * ```
     * 
     * @param int $button The mouse button to check
     * @return bool True if the mouse button was pressed, false otherwise
     */
    public function hasMouseButtonBeenPressedThisFrame(int $button) : bool
    {
        return $this->mouseButtonsDidPressFrame[$button] ?? false;
    }

    /**
     * Returns boolean if the given mouse button was released since the last frame
     * 
     * Example:
     * ```php
     * $input->hasMouseButtonBeenReleasedThisFrame(MouseButton::LEFT);
     * ```
     * 
     * @param int $button The mouse button to check
     * @return bool True if the mouse button was released, false otherwise
     */
    public function hasMouseButtonBeenReleasedThisFrame(int $button) : bool
    {
        return $this->mouseButtonsDidReleaseFrame[$button] ?? false;
    }

    /**
     * Get the current cursor position
     * 
     * @return Vec2 The current cursor position
     */
    public function getCursorPosition() : Vec2
    {
        $x = 0.0;
        $y = 0.0;
        glfwGetCursorPos($this->glfwWindowHandle, $x, $y);

        return new Vec2($x, $y);
    }

    /**
     * Returns the normalized cursor position
     * 
     * This is the cursor position normalized to the range of -1.0 to 1.0
     * 
     * @return Vec2 The normalized cursor position
     */
    public function getNormalizedCursorPosition() : Vec2
    {
        $w = 0.0;
        $h = 0.0;
        glfwGetWindowSize($this->glfwWindowHandle, $w, $h);
        $cursorPosition = $this->getCursorPosition();

        return new Vec2(
            $cursorPosition->x / ($w * 0.5) - 1.0,
            $cursorPosition->y / ($h * 0.5) - 1.0
        );
    }

    /**
     * Get the last recieved cursor position
     * This represents the last position the cursor was at before the current position,
     * !Note: This will be overwritten after the `input.cursor` events are dispatched.
     */
    public function getLastCursorPosition() : Vec2
    {
        return $this->lastCursorPosition;
    }

    /**
     * Returns the last mouse down position
     */
    public function getLastLeftMouseDownPosition() : Vec2
    {
        return $this->lastLeftMouseDownPosition;
    }

    /**
     * Returns the last mouse release position
     */
    public function getLastLeftMouseReleasePosition() : Vec2
    {
        return $this->lastLeftMouseReleasePosition;
    }


    /**
     * Set the cursor position
     * 
     * @param Vec2 $position The position to set the cursor to
     * @return void
     */
    public function setCursorPosition(Vec2 $position) : void
    {
        glfwSetCursorPos($this->glfwWindowHandle, $position->x, $position->y);
    }

    /**
     * Set the cursor mode
     * 
     * Available modes:
     * - `CursorMode::NORMAL`
     * - `CursorMode::HIDDEN`
     * - `CursorMode::DISABLED`
     * 
     * @param CursorMode $mode The mode to set the cursor to
     * @return void
     */
    public function setCursorMode(CursorMode $mode) : void
    {
        glfwSetInputMode($this->glfwWindowHandle, GLFW_CURSOR, $mode->value);
    }

    /**
     * Get the current cursor mode
     * 
     * @return CursorMode The current cursor mode
     */
    public function getCursorMode() : CursorMode
    {
        return CursorMode::from(glfwGetInputMode($this->glfwWindowHandle, GLFW_CURSOR));
    }

    /**
     * Window key event callback 
     * This method is invoked when a key is pressed, repeated or released.
     * 
     * @param Window $window The window that received the event
     * @param int $key The key that was pressed, repeated or released
     * @param int $scancode The system-specific scancode of the key
     * @param int $action The key action. One of: GLFW_PRESS, GLFW_RELEASE or GLFW_REPEAT
     * @param int $mods Bit field describing which modifier keys were held down
     * 
     * @return void 
     */
    public function handleWindowKey(Window $window, int $key, int $scancode, int $action, int $mods): void
    {
        $this->dispatcher->dispatch(self::EVENT_KEY, new KeySignal($window, $key, $scancode, $action, $mods));
    }

    /**
     * Window char event callback
     * This method is invoked when a character is inputted (e.g. when typing).
     * 
     * @param Window $window The window that received the event
     * @param int $char The Unicode code point of the character
     * 
     * @return void
     */
    public function handleWindowChar(Window $window, int $char): void
    {
        $this->dispatcher->dispatch(self::EVENT_CHAR, new CharSignal($window, $char));
    }

    /**
     * Window char mods event callback
     * This method is invoked when a character is inputted (e.g. when typing).
     * 
     * @param Window $window The window that received the event
     * @param int $char The Unicode code point of the character
     * @param int $mods Bit field describing which modifier keys were held down
     * 
     * @return void
     */
    public function handleWindowCharMods(Window $window, int $char, int $mods): void
    {
        $this->dispatcher->dispatch(self::EVENT_CHAR_MOD, new CharModSignal($window, $char, $mods));
    }

    /**
     * Window mouse button event callback
     * This method is invoked when a mouse button is pressed or released.
     * 
     * @param Window $window The window that received the event
     * @param int $button The mouse button that was pressed or released
     * @param int $action The mouse button action. One of: GLFW_PRESS or GLFW_RELEASE
     * @param int $mods Bit field describing which modifier keys were held down
     * 
     * @return void
     */
    public function handleWindowMouseButton(Window $window, int $button, int $action, int $mods): void
    {
        // record for did press and did release
        if ($action === GLFW_PRESS) {
            $this->mouseButtonsDidPress[$button] = true;
            $this->mouseButtonsDidPressFrame[$button] = true;
        } else if ($action === GLFW_RELEASE) {
            $this->mouseButtonsDidRelease[$button] = true;
            $this->mouseButtonsDidReleaseFrame[$button] = true;
        }

        // dispatch event
        $this->dispatcher->dispatch(self::EVENT_MOUSE_BUTTON, new MouseButtonSignal($window, $button, $action, $mods));

        // generate mouse click events if the mouse button is released
        if ($button == GLFW_MOUSE_BUTTON_LEFT) {
            if ($action == GLFW_PRESS) {
                $this->lastLeftMouseDownPosition = $this->lastCursorPosition->copy();
            } else if ($action == GLFW_RELEASE) {
                $this->lastLeftMouseReleasePosition = $this->lastCursorPosition->copy();    

                $currentPos = $this->lastCursorPosition->copy();
                $dist = $currentPos->distanceTo($this->lastLeftMouseDownPosition);

                if ($dist < $this->mouseClickMaxDistanceFromStart) {
                    $this->dispatcher->dispatch(self::EVENT_MOUSE_CLICK, new MouseClickSignal(
                        $window, 
                        $mods, 
                        $currentPos, 
                        $this->lastLeftMouseDownPosition->copy(), 
                        $dist
                    ));
                }
            }
        }
    }

    /**
     * Window cursor position event callback
     * This method is invoked when the cursor is moved.
     * 
     * @param Window $window The window that received the event
     * @param float $xpos The new x-coordinate, in screen coordinates, of the cursor
     * @param float $ypos The new y-coordinate, in screen coordinates, of the cursor
     * 
     * @return void
     */
    public function handleWindowCursorPos(Window $window, float $xpos, float $ypos): void
    {
        $offsetX = $xpos - $this->lastCursorPosition->x;
        $offsetY = $ypos - $this->lastCursorPosition->y;

        $this->dispatcher->dispatch(self::EVENT_CURSOR, new CursorPosSignal($window, $xpos, $ypos, $offsetX, $offsetY));

        // update the last cursor position
        $this->lastCursorPosition->x = $xpos;
        $this->lastCursorPosition->y = $ypos;
    }

    /**
     * Window cursor enter event callback
     * This method is invoked when the cursor enters or leaves the client area of the window.
     * 
     * @param Window $window The window that received the event
     * @param int $entered True if the cursor entered the window's client area, or false if it left it
     * 
     * @return void
     */
    public function handleWindowCursorEnter(Window $window, int $entered): void
    {
        $this->dispatcher->dispatch(self::EVENT_CURSOR_ENTER, new CursorEnterSignal($window, $entered));
    }

    /**
     * Window scroll event callback
     * This method is invoked when a scrolling device is used, such as a mouse wheel or scrolling area of a touchpad.
     * 
     * @param Window $window The window that received the event
     * @param float $xoffset The scroll offset along the x-axis
     * @param float $yoffset The scroll offset along the y-axis
     * 
     * @return void
     */
    public function handleWindowScroll(Window $window, float $xoffset, float $yoffset): void
    {
        $this->dispatcher->dispatch(self::EVENT_SCROLL, new ScrollSignal($window, $xoffset, $yoffset));
    }

    /**
     * Window drop event callback
     * This method is invoked when one or more dragged files are dropped on the window.
     * 
     * @param Window $window The window that received the event
     * @param array<string> $paths The UTF-8 encoded file and/or directory path names
     * 
     * @return void
     */
    public function handleWindowDrop(Window $window, array $paths): void
    {
        $this->dispatcher->dispatch(self::EVENT_DROP, new DropSignal($window, $paths));
    }

    /**
     * Callback before new evenets are polled
     * This method is invoked before new events are polled think of it as a pre-update method.
     * 
     * @param Window $window The window that received the event
     * @return void
     */
    public function handleWindowWillPollEvents(Window $window): void
    {
        $this->mouseButtonsDidPress = [];
        $this->mouseButtonsDidRelease = [];
    }

    /**
     * Callback after new evenets are polled
     * This method is invoked after new events are polled think of it as a post-update method.
     * 
     * @param Window $window The window that received the event
     * @return void
     */
    public function handleWindowDidPollEvents(Window $window): void
    {
    }

    /**
     * Invoke this method at the end of a frame to let the input manager
     * apply state changes to the per frame state.
     * 
     * @return void 
     */
    public function endFrame(): void
    {
        $this->mouseButtonsDidPressFrame = [];
        $this->mouseButtonsDidReleaseFrame = [];
    }

    /**
     * Is the input context currently claimed?
     */
    public function isContextUnclaimed(): bool
    {
        return $this->currentInputContext === null;
    }

    /**
     * Claim the input context
     * 
     * @param string $context The context string to claim
     * @return void
     */
    public function claimContext(string $context): void
    {
        if ($this->currentInputContext !== null) {
            throw new \Exception("Input context already claimed by: " . $this->currentInputContext);
        }

        $this->currentInputContext = $context;
    }

    /**
     * Release the input context
     * 
     * @param string $context The context string to release
     * @return void
     */
    public function releaseContext(string $context): void
    {
        if ($this->currentInputContext !== $context) {
            throw new \Exception("Input context already claimed by: " . $this->currentInputContext);
        }

        $this->currentInputContext = null;
    }

    /**
     * Get the current input context
     * 
     * @return string|null The current input context or null if no context is claimed
     */
    public function getCurrentContext(): ?string
    {
        return $this->currentInputContext;
    }

    /**
     * Returns true if the given input context is currently claimed
     * 
     * @param string $context The context string to check
     * @return bool True if the given input context is currently claimed
     */
    public function isClaimedContext(string $context): bool
    {
        return $this->currentInputContext === $context;
    }
}