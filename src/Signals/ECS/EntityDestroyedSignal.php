<?php

namespace VISU\Signals\ECS;

use VISU\Signal\Signal;

class EntityDestroyedSignal extends Signal
{
    public function __construct(
        public readonly int $entityId,
    ) {
    }
}
