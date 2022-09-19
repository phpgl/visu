<?php 

namespace VISU\OS;

class WindowEventCallbackHandler implements WindowEventHandlerInterface
{
    const EVENT_TYPE_KEY = 0;
    const EVENT_TYPE_CHAR = 1;

    /**
     * A counter that ensures registered callbacks dont share the same ID
     * 
     * @var int
     */
    private int $eventRegistrationIndex = 0;

    /**
     * Registered callbacks 
     * 
     * @var array<int, array<callable>>
     */
    private $eventHandlers = [
        self::EVENT_TYPE_KEY => [],
        self::EVENT_TYPE_CHAR => [],
    ];

    /**
     * Window key event callback 
     * This method is invoked when a key is pressed, repeated or released.
     * 
     * @param Window $window The window that received the event
     * @param int $key The key that was pressed, repeated or released
     * @param int $scancode The system-specific scancode of the key
     * @param int $action The key action. One of: GLFW_PRESS, GLFW_RELEASE or GLFW_REPEAT
     * @param int $mods Bit field describing which modifier keys were held down
     * @return void 
     */
    public function handleWindowKey(Window $window, int $key, int $scancode, int $action, int $mods): void
    {
        foreach ($this->eventHandlers[self::EVENT_TYPE_KEY] as $callback) {
            $callback($window, $key, $scancode, $action, $mods);
        }
    }

    /**
     * Register a callback for the window key event
     * 
     * @param callable $callback 
     * @return int The ID of the registered callback, store this if you want to unregister the callback later
     */
    public function onWindowKey(callable $callback): int
    {
        $this->eventHandlers[self::EVENT_TYPE_KEY][$this->eventRegistrationIndex++] = $callback;
        return $this->eventRegistrationIndex;
    }

    /**
     * Unregister a callback for the window key event
     * 
     * @param int $id The ID of the callback to unregister
     * @return void
     */
    public function unbindWindowKeyCallback(int $id): void
    {
        unset($this->eventHandlers[self::EVENT_TYPE_KEY][$id]);
    }

    /**
     * Window char event callback
     * This method is invoked when a character is inputted (e.g. when typing).
     * 
     * @param Window $window The window that received the event
     * @param int $char The Unicode code point of the character
     * @return void
     */
    public function handleWindowChar(Window $window, int $char): void
    {
        foreach ($this->eventHandlers[self::EVENT_TYPE_CHAR] as $callback) {
            $callback($window, $char);
        }
    }

    /**
     * Register a callback for the window char event
     * 
     * @param callable $callback 
     * @return int The ID of the registered callback, store this if you want to unregister the callback later
     */
    public function onWindowChar(callable $callback): int
    {
        $this->eventHandlers[self::EVENT_TYPE_CHAR][$this->eventRegistrationIndex++] = $callback;
        return $this->eventRegistrationIndex;
    }

    /**
     * Unregister a callback for the window char event
     * 
     * @param int $id The ID of the callback to unregister
     * @return void
     */
    public function unbindWindowCharCallback(int $id): void
    {
        unset($this->eventHandlers[self::EVENT_TYPE_CHAR][$id]);
    }
}