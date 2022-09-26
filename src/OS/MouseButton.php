<?php 

namespace VISU\OS;

class MouseButton
{
    /**
     * We warp the GLFW mouse button constants to allow a syntax like this:
     * 
     * ```php
     * $input->getMouseButtonState(MouseButton::LEFT) === INPUT::PRESS
     * ```
     */
    // mouse buttons
    public const LAST = GLFW_MOUSE_BUTTON_LAST;
    public const LEFT = GLFW_MOUSE_BUTTON_LEFT;
    public const RIGHT = GLFW_MOUSE_BUTTON_RIGHT;
    public const MIDDLE = GLFW_MOUSE_BUTTON_MIDDLE;
    public const BUTTON_1 = GLFW_MOUSE_BUTTON_1;
    public const BUTTON_2 = GLFW_MOUSE_BUTTON_2;
    public const BUTTON_3 = GLFW_MOUSE_BUTTON_3;
    public const BUTTON_4 = GLFW_MOUSE_BUTTON_4;
    public const BUTTON_5 = GLFW_MOUSE_BUTTON_5;
    public const BUTTON_6 = GLFW_MOUSE_BUTTON_6;
    public const BUTTON_7 = GLFW_MOUSE_BUTTON_7;
    public const BUTTON_8 = GLFW_MOUSE_BUTTON_8;
}