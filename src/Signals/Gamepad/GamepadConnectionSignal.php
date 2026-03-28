<?php

namespace VISU\Signals\Gamepad;

use VISU\Signal\Signal;

class GamepadConnectionSignal extends Signal
{
    public function __construct(
        public readonly int $gamepadIndex,
        public readonly bool $connected,
        public readonly string $name,
    ) {}
}
