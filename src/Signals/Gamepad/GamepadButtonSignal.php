<?php

namespace VISU\Signals\Gamepad;

use VISU\OS\GamepadButton;
use VISU\Signal\Signal;

class GamepadButtonSignal extends Signal
{
    public function __construct(
        public readonly int $gamepadIndex,
        public readonly GamepadButton $button,
        public readonly bool $pressed,
    ) {}
}
