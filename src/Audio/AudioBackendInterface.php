<?php

namespace VISU\Audio;

interface AudioBackendInterface
{
    /**
     * Load a WAV file and return raw PCM data as an AudioClipData value object.
     */
    public function loadWav(string $path): AudioClipData;

    /**
     * Play a clip (one-shot, fire-and-forget).
     */
    public function play(AudioClipData $clip, float $volume = 1.0): void;

    /**
     * Start streaming a clip for music playback (loopable).
     * Returns an opaque handle for stream management.
     */
    public function streamStart(AudioClipData $clip): int;

    /**
     * Query how many bytes are still queued/buffered for a stream handle.
     */
    public function streamQueued(int $handle): int;

    /**
     * Re-queue data into an active stream (for looping).
     */
    public function streamEnqueue(int $handle, AudioClipData $clip): void;

    /**
     * Set the volume of an active stream (0.0 to 1.0).
     */
    public function streamSetVolume(int $handle, float $volume): void;

    /**
     * Stop and destroy a stream handle.
     */
    public function streamStop(int $handle): void;

    /**
     * Clean up all resources.
     */
    public function shutdown(): void;

    /**
     * Get the backend name for debugging.
     */
    public function getName(): string;
}
