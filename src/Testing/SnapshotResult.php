<?php

namespace VISU\Testing;

class SnapshotResult
{
    public function __construct(
        public readonly bool $passed,
        public readonly float $diffPercent,
        public readonly float $threshold,
        public readonly string $snapshotName,
        public readonly ?string $referencePath,
        public readonly ?string $actualPath,
        public readonly ?string $diffPath,
        public readonly bool $isNew,
    ) {}
}
