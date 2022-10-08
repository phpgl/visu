<?php 

namespace VISU\Signals\Input;

use VISU\OS\Window;
use VISU\Signal\Signal;

class CursorPosSignal extends Signal
{
    /**
     * The window that received the event
     * 
     * @var Window
     */
    public readonly Window $window;

    /**
     * The new cursor x-coordinate, relative to the left edge of the content area
     * 
     * @var float
     */
    public readonly float $x;

    /**
     * The new cursor y-coordinate, relative to the top edge of the content area
     * 
     * @var float
     */
    public readonly float $y;

    /**
     * Constructor
     * 
     * @param Window $window The window that received the event
     * @param float $x The new cursor x-coordinate, relative to the left edge of the content area
     * @param float $y The new cursor y-coordinate, relative to the top edge of the content area
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
}