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
    
    public function isShiftDown(): bool
    {
        return ($this->mods & GLFW_MOD_SHIFT) === GLFW_MOD_SHIFT;
    }

    public function isControlDown(): bool
    {
        return ($this->mods & GLFW_MOD_CONTROL) === GLFW_MOD_CONTROL;
    }

    public function isAltDown(): bool
    {
        return ($this->mods & GLFW_MOD_ALT) === GLFW_MOD_ALT;
    }

    public function isSuperDown(): bool
    {
        return ($this->mods & GLFW_MOD_SUPER) === GLFW_MOD_SUPER;
    }

    public function isCapsLockOn(): bool
    {
        return ($this->mods & GLFW_MOD_CAPS_LOCK) === GLFW_MOD_CAPS_LOCK;
    }

    public function isNumLockOn(): bool
    {
        return ($this->mods & GLFW_MOD_NUM_LOCK) === GLFW_MOD_NUM_LOCK;
    }

    /**
     * Omits the window property from the debug output
     * 
     * @return array<string, mixed>
     */
    public function __debugInfo()
    {
        return [
            'key' => $this->key,
            'scancode' => $this->scancode,
            'action' => $this->action,
            'mods' => $this->mods,
        ];
    }   
}
