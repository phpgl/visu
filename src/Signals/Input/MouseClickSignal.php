<?php 

namespace VISU\Signals\Input;

use GL\Math\Vec2;
use VISU\OS\Window;
use VISU\Signal\Signal;

class MouseClickSignal extends Signal
{
    /**
     * The window that received the event
     */
    public readonly Window $window;

    /**
     * Bit field describing which modifier keys were held down
     */
    public readonly int $mods;

    /**
     * Position the cursor currently is at
     */
    public readonly Vec2 $position;

    /**
     * Position the cursor was at when the mouse button was pressed initially
     */
    public readonly Vec2 $initialPosition;

    /**
     * Distance the cursor has moved since the mouse button was pressed initially
     */
    public readonly float $travelDistance;

    /**
     * Constructor
     * 
     * @param Window $window The window that received the event
     * @param int $mods Bit field describing which modifier keys were held down
     * @param Vec2 $position Position the cursor currently is at
     * @param Vec2 $initialPosition Position the cursor was at when the mouse button was pressed initially
     * @param float $travelDistance Distance the cursor has moved since the mouse button was pressed initially
     */
    public function __construct(
        Window $window,
        int $mods,
        Vec2 $position,
        Vec2 $initialPosition,
        float $travelDistance
    ) {
        $this->window = $window;
        $this->mods = $mods;
        $this->position = $position;
        $this->initialPosition = $initialPosition;
        $this->travelDistance = $travelDistance;
    }

    /**
     * Omits the window property from the debug output
     */
    public function __debugInfo()
    {
        return [
            'mods' => $this->mods,
            'position' => (string) $this->position,
            'initialPosition' => (string) $this->initialPosition,
            'travelDistance' => $this->travelDistance,
        ];
    }   
}