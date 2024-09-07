<?php

namespace VISU\Graphics;

enum CameraProjectionMode 
{
    /**
     * Perspective projection mode
     */
    case perspective;

    /**
     * Orthographic World
     * 
     * Relies on screen resolution and content scale but 0,0 is always in the center of the screen
     */
    case orthographicWorld;

    /**
     * Orthographic Screen
     * 
     * Relies on screen resolution and content scale: 0,0 is always in the top left corner
     * Y+ is always down.
     */
    case orthographicScreen;

    /**
     * Orthographic Static World
     * 
     * World relies on $staticWorldHeight and aspect ratio is based on screen resolution
     * This mode is what you want for 2D games where you want to have a fixed coordinate system
     * For example side scrollers.
     */
    case orthographicStaticWorld;
}