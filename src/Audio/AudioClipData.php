<?php

namespace VISU\Audio;

/**
 * Backend-agnostic audio clip data.
 * Holds raw PCM samples as a PHP string buffer.
 */
class AudioClipData
{
    public function __construct(
        public readonly string $pcmData,
        public readonly int $sampleRate,
        public readonly int $channels,
        public readonly int $bitsPerSample,
        public readonly string $sourcePath,
    ) {}

    public function getByteLength(): int
    {
        return strlen($this->pcmData);
    }
}
