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
     * The offset in x-direction since the last cursor position event
     * 
     * @var float
     */
    public readonly float $offsetX;

    /**
     * The offset in y-direction since the last cursor position event
     * 
     * @var float
     */
    public readonly float $offsetY;

    /**
     * Constructor
     * 
     * @param Window $window The window that received the event
     * @param float $x The new cursor x-coordinate, relative to the left edge of the content area
     * @param float $y The new cursor y-coordinate, relative to the top edge of the content area
     * @param float $offsetX The offset in x-direction since the last cursor position event
     * @param float $offsetY The offset in y-direction since the last cursor position event
     */
    public function __construct(
        Window $window,
        float $x,
        float $y,
        float $offsetX,
        float $offsetY,
    ) {
        $this->window = $window;
        $this->x = $x;
        $this->y = $y;
        $this->offsetX = $offsetX;
        $this->offsetY = $offsetY;
    }

    /**
     * Omits the window property from the debug output
     * 
     * @return array<string, mixed>
     */
    public function __debugInfo()
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
        ];
    }   
}