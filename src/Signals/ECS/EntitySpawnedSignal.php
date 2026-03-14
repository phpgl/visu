<?php

namespace VISU\Signals\ECS;

use VISU\Signal\Signal;

class EntitySpawnedSignal extends Signal
{
    public function __construct(
        public readonly int $entityId,
        public readonly ?string $entityName = null,
    ) {
    }
}
