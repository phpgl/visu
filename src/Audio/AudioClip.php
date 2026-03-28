<?php

namespace VISU\Audio;

use FFI\CData;

class AudioClip
{
    public function __construct(
        public readonly CData $spec,
        public readonly CData $buffer,
        public readonly int $length,
        public readonly string $path,
    ) {}
}
