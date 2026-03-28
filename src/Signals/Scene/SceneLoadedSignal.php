<?php

namespace VISU\Signals\Scene;

use VISU\Signal\Signal;

class SceneLoadedSignal extends Signal
{
    public function __construct(
        public readonly string $sceneName,
        public readonly string $scenePath,
    ) {
    }
}
