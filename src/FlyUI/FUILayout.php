<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;

class FUILayout extends FUIView
{
    public ?float $width = null;
    public ?float $height = null;
    public ?float $left = null;
    public ?float $top = null;
    public ?float $right = null;
    public ?float $bottom = null;

    public function __construct()
    {
        parent::__construct(new Vec2(0, 0));
    }
}