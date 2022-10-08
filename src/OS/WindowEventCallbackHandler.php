<?php 

namespace VISU\OS;

class WindowEventCallbackHandler implements WindowEventHandlerInterface
{
    const EVENT_TYPE_KEY = 0;
    const EVENT_TYPE_CHAR = 1;
    const EVENT_TYPE_CHAR_MODS = 2;
    const EVENT_TYPE_MOUSE_BUTTON = 3;
    const EVENT_TYPE_CURSOR_POSITION = 4;
    const EVENT_TYPE_CURSOR_ENTER = 5;
    const EVENT_TYPE_SCROLL = 6;
    const EVENT_TYPE_DROP = 7;

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
        self::EVENT_TYPE_CHAR_MODS => [],
        self::EVENT_TYPE_MOUSE_BUTTON => [],
        self::EVENT_TYPE_CURSOR_POSITION => [],
        self::EVENT_TYPE_CURSOR_ENTER => [],
        self::EVENT_TYPE_DROP => [],
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


    /**
     * Window char mods event callback
     * This method is invoked when a character is inputted (e.g. when typing).
     * 
     * @param Window $window The window that received the event
     * @param int $char The Unicode code point of the character
     * @param int $mods Bit field describing which modifier keys were held down
     * @return void
     */
    public function handleWindowCharMods(Window $window, int $char, int $mods): void
    {
        foreach ($this->eventHandlers[self::EVENT_TYPE_CHAR_MODS] as $callback) {
            $callback($window, $char, $mods);
        }
    }

    /**
     * Register a callback for the window char mods event
     * 
     * @param callable $callback
     * @return int The ID of the registered callback, store this if you want to unregister the callback later
     */
    public function onWindowCharMods(callable $callback): int
    {
        $this->eventHandlers[self::EVENT_TYPE_CHAR_MODS][$this->eventRegistrationIndex++] = $callback;
        return $this->eventRegistrationIndex;
    }

    /**
     * Unregister a callback for the window char mods event
     * 
     * @param int $id The ID of the callback to unregister
     * @return void
     */
    public function unbindWindowCharModsCallback(int $id): void
    {
        unset($this->eventHandlers[self::EVENT_TYPE_CHAR_MODS][$id]);
    }

    /**
     * Window mouse button event callback
     * This method is invoked when a mouse button is pressed or released.
     * 
     * @param Window $window The window that received the event
     * @param int $button The mouse button that was pressed or released
     * @param int $action The mouse button action. One of: GLFW_PRESS or GLFW_RELEASE
     * @param int $mods Bit field describing which modifier keys were held down
     * @return void
     */
    public function handleWindowMouseButton(Window $window, int $button, int $action, int $mods): void
    {
        foreach ($this->eventHandlers[self::EVENT_TYPE_MOUSE_BUTTON] as $callback) {
            $callback($window, $button, $action, $mods);
        }
    }

    /**
     * Register a callback for the window mouse button event
     * 
     * @param callable $callback
     * @return int The ID of the registered callback, store this if you want to unregister the callback later
     */
    public function onWindowMouseButton(callable $callback): int
    {
        $this->eventHandlers[self::EVENT_TYPE_MOUSE_BUTTON][$this->eventRegistrationIndex++] = $callback;
        return $this->eventRegistrationIndex;
    }

    /**
     * Unregister a callback for the window mouse button event
     * 
     * @param int $id The ID of the callback to unregister
     * @return void
     */
    public function unbindWindowMouseButtonCallback(int $id): void
    {
        unset($this->eventHandlers[self::EVENT_TYPE_MOUSE_BUTTON][$id]);
    }

    /**
     * Window cursor position event callback
     * This method is invoked when the cursor is moved.
     * 
     * @param Window $window The window that received the event
     * @param float $xpos The new x-coordinate, in screen coordinates, of the cursor
     * @param float $ypos The new y-coordinate, in screen coordinates, of the cursor
     * @return void
     */
    public function handleWindowCursorPos(Window $window, float $xpos, float $ypos): void
    {
        foreach ($this->eventHandlers[self::EVENT_TYPE_CURSOR_POSITION] as $callback) {
            $callback($window, $xpos, $ypos);
        }
    }

    /**
     * Register a callback for the window cursor position event
     * 
     * @param callable $callback
     * @return int The ID of the registered callback, store this if you want to unregister the callback later
     */
    public function onWindowCursorPos(callable $callback): int
    {
        $this->eventHandlers[self::EVENT_TYPE_CURSOR_POSITION][$this->eventRegistrationIndex++] = $callback;
        return $this->eventRegistrationIndex;
    }

    /**
     * Unregister a callback for the window cursor position event
     * 
     * @param int $id The ID of the callback to unregister
     * @return void
     */
    public function unbindWindowCursorPosCallback(int $id): void
    {
        unset($this->eventHandlers[self::EVENT_TYPE_CURSOR_POSITION][$id]);
    }

    /**
     * Window cursor enter event callback
     * This method is invoked when the cursor enters or leaves the client area of the window.
     * 
     * @param Window $window The window that received the event
     * @param int $entered Whether the cursor entered or left the window
     * @return void
     */
    public function handleWindowCursorEnter(Window $window, int $entered): void
    {
        foreach ($this->eventHandlers[self::EVENT_TYPE_CURSOR_ENTER] as $callback) {
            $callback($window, $entered);
        }
    }

    /**
     * Register a callback for the window cursor enter event
     * 
     * @param callable $callback
     * @return int The ID of the registered callback, store this if you want to unregister the callback later
     */
    public function onWindowCursorEnter(callable $callback): int
    {
        $this->eventHandlers[self::EVENT_TYPE_CURSOR_ENTER][$this->eventRegistrationIndex++] = $callback;
        return $this->eventRegistrationIndex;
    }

    /**
     * Unregister a callback for the window cursor enter event
     * 
     * @param int $id The ID of the callback to unregister
     * @return void
     */
    public function unbindWindowCursorEnterCallback(int $id): void
    {
        unset($this->eventHandlers[self::EVENT_TYPE_CURSOR_ENTER][$id]);
    }

    /**
     * Window scroll event callback
     * This method is invoked when a scrolling device is used, such as a mouse wheel or scrolling area of a touchpad.
     * 
     * @param Window $window The window that received the event
     * @param float $xoffset The scroll offset along the x-axis
     * @param float $yoffset The scroll offset along the y-axis
     * @return void
     */
    public function handleWindowScroll(Window $window, float $xoffset, float $yoffset): void
    {
        foreach ($this->eventHandlers[self::EVENT_TYPE_SCROLL] as $callback) {
            $callback($window, $xoffset, $yoffset);
        }
    }

    /**
     * Register a callback for the window scroll event
     * 
     * @param callable $callback
     * @return int The ID of the registered callback, store this if you want to unregister the callback later
     */
    public function onWindowScroll(callable $callback): int
    {
        $this->eventHandlers[self::EVENT_TYPE_SCROLL][$this->eventRegistrationIndex++] = $callback;
        return $this->eventRegistrationIndex;
    }

    /**
     * Unregister a callback for the window scroll event
     * 
     * @param int $id The ID of the callback to unregister
     * @return void
     */
    public function unbindWindowScrollCallback(int $id): void
    {
        unset($this->eventHandlers[self::EVENT_TYPE_SCROLL][$id]);
    }

    /**
     * Window drop event callback
     * This method is invoked when one or more dragged files are dropped on the window.
     * 
     * @param Window $window The window that received the event
     * @param array<string> $paths The UTF-8 encoded file and/or directory path names
     * @return void
     */
    public function handleWindowDrop(Window $window, array $paths): void
    {
        foreach ($this->eventHandlers[self::EVENT_TYPE_DROP] as $callback) {
            $callback($window, $paths);
        }
    }

    /**
     * Register a callback for the window drop event
     * 
     * @param callable $callback
     * @return int The ID of the registered callback, store this if you want to unregister the callback later
     */
    public function onWindowDrop(callable $callback): int
    {
        $this->eventHandlers[self::EVENT_TYPE_DROP][$this->eventRegistrationIndex++] = $callback;
        return $this->eventRegistrationIndex;
    }

    /**
     * Unregister a callback for the window drop event
     * 
     * @param int $id The ID of the callback to unregister
     * @return void
     */
    public function unbindWindowDropCallback(int $id): void
    {
        unset($this->eventHandlers[self::EVENT_TYPE_DROP][$id]);
    }
}