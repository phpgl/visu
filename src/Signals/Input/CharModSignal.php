<?php 

namespace VISU\Signals\Input;

use VISU\OS\Window;
use VISU\Signal\Signal;

class CharModSignal extends Signal
{
    /**
     * The window that received the event
     * 
     * @var Window
     */
    public readonly Window $window;

    /**
     * The Unicode code point of the character
     * 
     * @var int
     */
    public readonly int $codepoint;

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
     * @param int $codepoint The Unicode code point of the character
     * @param int $mods Bit field describing which modifier keys were held down
     */
    public function __construct(
        Window $window,
        int $codepoint,
        int $mods
    ) {
        $this->window = $window;
        $this->codepoint = $codepoint;
        $this->mods = $mods;
    }

    /**
     * Returns the received character as a string
     * 
     * @param null|string $encoding 
     * @return string 
     */
    public function getString(?string $encoding = null): string
    {
        return mb_chr($this->codepoint, $encoding) ?: '';
    }
}
