<?php 

namespace VISU\OS;

interface WindowEventHandlerInterface
{
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
    public function handleWindowKey(Window $window, int $key, int $scancode, int $action, int $mods): void;

    /**
     * Window char event callback
     * This method is invoked when a character is inputted (e.g. when typing).
     * 
     * @param Window $window The window that received the event
     * @param int $char The Unicode code point of the character
     * @return void
     */
    public function handleWindowChar(Window $window, int $char): void;

    /**
     * Window char mods event callback
     * This method is invoked when a character is inputted (e.g. when typing).
     * 
     * @param Window $window The window that received the event
     * @param int $char The Unicode code point of the character
     * @param int $mods Bit field describing which modifier keys were held down
     */
    public function handleWindowCharMods(Window $window, int $char, int $mods): void;

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
    public function handleWindowMouseButton(Window $window, int $button, int $action, int $mods): void;

    /**
     * Window cursor position event callback
     * This method is invoked when the cursor is moved.
     * 
     * @param Window $window The window that received the event
     * @param float $xpos The new x-coordinate, in screen coordinates, of the cursor
     * @param float $ypos The new y-coordinate, in screen coordinates, of the cursor
     * @return void
     */
    public function handleWindowCursorPos(Window $window, float $xpos, float $ypos): void;

    /**
     * Window cursor enter event callback
     * This method is invoked when the cursor enters or leaves the client area of the window.
     * 
     * @param Window $window The window that received the event
     * @param int $entered One of: GLFW_TRUE or GLFW_FALSE
     * @return void
     */
    public function handleWindowCursorEnter(Window $window, int $entered): void;

    /**
     * Window scroll event callback
     * This method is invoked when a scrolling device is used, such as a mouse wheel or scrolling area of a touchpad.
     * 
     * @param Window $window The window that received the event
     * @param float $xoffset The scroll offset along the x-axis
     * @param float $yoffset The scroll offset along the y-axis
     * @return void
     */
    public function handleWindowScroll(Window $window, float $xoffset, float $yoffset): void;

    /**
     * Window drop event callback
     * This method is invoked when one or more dragged files are dropped on the window.
     * 
     * @param Window $window The window that received the event
     * @param array<string> $paths The UTF-8 encoded file and/or directory path names
     * @return void
     */
    public function handleWindowDrop(Window $window, array $paths): void;

}