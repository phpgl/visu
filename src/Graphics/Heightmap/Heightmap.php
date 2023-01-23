<?php

namespace VISU\Graphics\Heightmap;

use GL\Buffer\FloatBuffer;

class Heightmap
{
    public function __construct(
        private FloatBuffer $data,
        private int $width,
        private int $height,
        private float $ppu = 1.0,
    )
    {
        
    }

    public function getHeightAt(float $x, float $y) : float
    {
        $x = $x * $this->ppu;
        $y = $y * $this->ppu;

        $x = $x + $this->width / 2;
        $y = $y + $this->height / 2;

        $x = (int) $x;
        $y = (int) $y;

        $index = $y * $this->width + $x;

        return $this->data[$index];
    }
}