<?php

namespace VISU\Save;

class SaveSlotInfo
{
    public function __construct(
        public readonly string $name,
        public readonly float $timestamp,
        public readonly float $playTime,
        public readonly string $description,
        public readonly int $version,
    ) {
    }
}
