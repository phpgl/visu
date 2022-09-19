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

}