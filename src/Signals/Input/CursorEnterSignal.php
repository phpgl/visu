<?php 

namespace VISU\Signals\Input;

use VISU\OS\Window;
use VISU\Signal\Signal;

class CursorEnterSignal extends Signal
{
    /**
     * The window that received the event
     * 
     * @var Window
     */
    public readonly Window $window;

    /**
     * The cursor enter/leave state
     * 
     * @var int
     */
    public readonly int $entered;

    /**
     * Constructor
     * 
     * @param Window $window The window that received the event
     * @param int $entered The cursor enter/leave state
     */
    public function __construct(
        Window $window,
        int $entered
    ) {
        $this->window = $window;
        $this->entered = $entered;
    }

    /**
     * Returns whether the cursor entered the window
     * 
     * @return bool 
     */
    public function entered(): bool
    {
        return $this->entered === GLFW_TRUE;
    }

    /**
     * Returns whether the cursor left the window
     * 
     * @return bool 
     */
    public function left(): bool
    {
        return $this->entered === GLFW_FALSE;
    }
}