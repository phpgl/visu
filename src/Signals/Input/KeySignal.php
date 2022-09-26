<?php 

namespace VISU\Signals\Input;

use VISU\OS\Window;
use VISU\Signal\Signal;

class KeySignal extends Signal
{
    /**
     * The window that received the event
     * 
     * @var Window
     */
    public readonly Window $window;

    /**
     * The key that was pressed, repeated or released
     * 
     * @var int
     */
    public readonly int $key;

    /**
     * The system-specific scancode of the key
     * 
     * @var int
     */
    public readonly int $scancode;

    /**
     * The key action. One of: GLFW_PRESS, GLFW_RELEASE or GLFW_REPEAT
     * 
     * @var int
     */
    public readonly int $action;

    /**
     * Bit field describing which modifier keys were held down
     * 
     * @var int
     */
    public readonly int $mods;

    /**
     * Constructor
     * 
     * @param Window $window The window that received the event
     * @param int $key The key that was pressed, repeated or released
     * @param int $scancode The system-specific scancode of the key
     * @param int $action The key action. One of: GLFW_PRESS, GLFW_RELEASE or GLFW_REPEAT
     * @param int $mods Bit field describing which modifier keys were held down
     */
    public function __construct(
        Window $window,
        int $key,
        int $scancode,
        int $action,
        int $mods
    ) {
        $this->window = $window;
        $this->key = $key;
        $this->scancode = $scancode;
        $this->action = $action;
        $this->mods = $mods;
    }
}
