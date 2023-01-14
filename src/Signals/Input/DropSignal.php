<?php 

namespace VISU\Signals\Input;

use VISU\OS\Window;
use VISU\Signal\Signal;

class DropSignal extends Signal
{
    /**
     * The window that received the event
     * 
     * @var Window
     */
    public readonly Window $window;

    /**
     * The UTF-8 encoded file and/or directory path names
     * 
     * @var array<string>
     */
    public readonly array $paths;

    /**
     * Constructor
     * 
     * @param Window $window The window that received the event
     * @param array<string> $paths The UTF-8 encoded file and/or directory path names
     */
    public function __construct(
        Window $window,
        array $paths
    ) {
        $this->window = $window;
        $this->paths = $paths;
    }   
}