<?php 

namespace VISU\Signals\Input;

use VISU\OS\Input;
use VISU\OS\MouseButton;
use VISU\OS\Window;
use VISU\Signal\Signal;

class MouseButtonSignal extends Signal
{
    /**
     * The window that received the event
     * 
     * @var Window
     */
    public readonly Window $window;

    /**
     * The mouse button that was pressed or released
     * 
     * @var int
     */
    public readonly int $button;

    /**
     * The action that was performed
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
     * @param int $button The mouse button that was pressed or released
     * @param int $action The action that was performed
     * @param int $mods Bit field describing which modifier keys were held down
     */
    public function __construct(
        Window $window,
        int $button,
        int $action,
        int $mods
    ) {
        $this->window = $window;
        $this->button = $button;
        $this->action = $action;
        $this->mods = $mods;
    }

    /**
     * Returns boolean if the left mouse button was pressed or released
     */
    public function isLeft(): bool
    {
        return $this->button === MouseButton::LEFT;
    }

    /**
     * Returns boolean if the right mouse button was pressed or released
     */
    public function isRight(): bool
    {
        return $this->button === MouseButton::RIGHT;
    }

    /**
     * Returns boolean if the middle mouse button was pressed or released
     */
    public function isMiddle(): bool
    {
        return $this->button === MouseButton::MIDDLE;
    }

    /**
     * Returns boolean if the left mouse button was pressed down
     */
    public function isLeftDown(): bool
    {
        return $this->button === MouseButton::LEFT && $this->action === Input::PRESS;
    }

    /**
     * Returns boolean if the right mouse button was pressed down
     */
    public function isRightDown(): bool
    {
        return $this->button === MouseButton::RIGHT && $this->action === Input::PRESS;
    }

    /**
     * Returns boolean if the middle mouse button was pressed down
     */
    public function isMiddleDown(): bool
    {
        return $this->button === MouseButton::MIDDLE && $this->action === Input::PRESS;
    }
}
