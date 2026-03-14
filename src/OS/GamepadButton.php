<?php

namespace VISU\OS;

enum GamepadButton: int
{
    case South        = 0;   // Xbox A / PS Cross
    case East         = 1;   // Xbox B / PS Circle
    case West         = 2;   // Xbox X / PS Square
    case North        = 3;   // Xbox Y / PS Triangle
    case Back         = 4;
    case Guide        = 5;
    case Start        = 6;
    case LeftStick    = 7;
    case RightStick   = 8;
    case LeftShoulder = 9;
    case RightShoulder = 10;
    case DPadUp       = 11;
    case DPadDown     = 12;
    case DPadLeft     = 13;
    case DPadRight    = 14;
    case Misc1        = 15;
    case Paddle1      = 16;
    case Paddle2      = 17;
    case Paddle3      = 18;
    case Paddle4      = 19;
    case Touchpad     = 20;
}
