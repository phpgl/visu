<?php

namespace VISU\Graphics\Rendering\Pass;

class Camera2DData
{
    public function __construct(
        public float $x = 0.0,
        public float $y = 0.0,
        public float $zoom = 1.0,
    ) {
    }
}
