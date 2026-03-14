<?php

namespace VISU\Signals\Save;

use VISU\Signal\Signal;

class SaveSignal extends Signal
{
    public const SAVE = 'save';
    public const LOAD = 'load';
    public const DELETE = 'delete';

    public function __construct(
        public readonly string $slotName,
        public readonly string $action,
    ) {
    }
}
