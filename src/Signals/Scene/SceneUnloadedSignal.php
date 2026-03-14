<?php

namespace VISU\Signals\Scene;

use VISU\Signal\Signal;

class SceneUnloadedSignal extends Signal
{
    public function __construct(
        public readonly string $sceneName,
    ) {
    }
}
