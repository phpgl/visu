<?php 

namespace VISU\Signals\Input;

use VISU\OS\Window;
use VISU\Signal\Signal;

class ScrollSignal extends Signal
{
    /**
     * The window that received the event
     * 
     * @var Window
     */
    public readonly Window $window;

    /**
     * The scroll offset along the x-axis
     * 
     * @var float
     */
    public readonly float $x;

    /**
     * The scroll offset along the y-axis
     * 
     * @var float
     */
    public readonly float $y;

    /**
     * Constructor
     * 
     * @param Window $window The window that received the event
     * @param float $x The scroll offset along the x-axis
     * @param float $y The scroll offset along the y-axis
     */
    public function __construct(
        Window $window,
        float $x,
        float $y
    ) {
        $this->window = $window;
        $this->x = $x;
        $this->y = $y;
    }

    /**
     * Omits the window property from the debug output
     */
    public function __debugInfo()
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
        ];
    }
}