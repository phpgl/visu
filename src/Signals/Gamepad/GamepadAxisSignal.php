<?php

namespace VISU\Signals\Gamepad;

use VISU\OS\GamepadAxis;
use VISU\Signal\Signal;

class GamepadAxisSignal extends Signal
{
    public function __construct(
        public readonly int $gamepadIndex,
        public readonly GamepadAxis $axis,
        public readonly float $value,
        public readonly int $rawValue,
    ) {}
}
