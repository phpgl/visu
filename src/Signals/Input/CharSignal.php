<?php 

namespace VISU\Signals\Input;

use VISU\OS\Window;
use VISU\Signal\Signal;

class CharSignal extends Signal
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
     * Constructor
     * 
     * @param Window $window The window that received the event
     * @param int $codepoint The Unicode code point of the character
     */
    public function __construct(
        Window $window,
        int $codepoint
    ) {
        $this->window = $window;
        $this->codepoint = $codepoint;
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
