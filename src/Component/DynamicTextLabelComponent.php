<?php

namespace VISU\Component;

use GL\Math\Vec4;

class DynamicTextLabelComponent 
{
    /**
     * Constructor
     */
    public function __construct(
        public string $text,
        public ?Vec4 $color = null,
        public ?string $fontHandle = null,
        public string $renderGroup = 'default',
    ) {
        $this->color ??= new Vec4(1, 1, 1, 1);
    }
}