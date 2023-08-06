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
        public readonly ?string $fontHandle = null,
        public readonly string $renderGroup = 'default',
        public readonly bool $isStatic = false,
    ) {
        $this->color ??= new Vec4(1, 1, 1, 1);
    }
}