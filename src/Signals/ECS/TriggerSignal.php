<?php

namespace VISU\Signals\ECS;

use VISU\Signal\Signal;

class TriggerSignal extends Signal
{
    public const ENTER = 'enter';
    public const STAY = 'stay';
    public const EXIT = 'exit';

    public function __construct(
        public readonly int $entityA,
        public readonly int $entityB,
        public readonly string $phase = self::ENTER,
    ) {
    }
}
