<?php 

namespace VISU\OS;

use GLFWwindow;
use VISU\Signal\DispatcherInterface;
use VISU\Signals\Input\CharSignal;
use VISU\Signals\Input\KeySignal;

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
     * GLFW window instance.
     * required to fetch key states. We copy a reference to the raw window
     * so we don't always have to use `getGLFWHandle` everytime we want to check 
     * a windows key state.
     * 
     * @var GLFWwindow
     */
    private \GLFWwindow $glfwWindowHandle;

    /**
     * Constructor for the Input class.
     * 
     * @param Window $window The window instance to handle input for.
     * @param DispatcherInterface $dispatcher The dispatcher instance events will be dispatched to.
     * @param bool $registerAsEventHandler If true, the input instance will register itself as an event handler for the window.
     * 
     * @return void 
     */
    public function __construct(
        private Window $window,
        private DispatcherInterface $dispatcher,
        bool $registerAsEventHandler = true
    ) {
        $this->glfwWindowHandle = $this->window->getGLFWHandle();

        if ($registerAsEventHandler) {
            $this->window->setEventHandler($this);
        }
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
        $this->dispatcher->dispatch("input.key", new KeySignal($window, $key, $scancode, $action, $mods));
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
        $this->dispatcher->dispatch("input.char", new CharSignal($window, $char));
    }
}