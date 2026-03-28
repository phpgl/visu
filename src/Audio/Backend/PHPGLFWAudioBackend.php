<?php

namespace VISU\Audio\Backend;

use GL\Audio\Engine as GLAudioEngine;
use GL\Audio\Sound as GLSound;
use VISU\Audio\AudioBackendInterface;
use VISU\Audio\AudioClipData;

/**
 * Audio backend using php-glfw's built-in audio engine (miniaudio).
 * Works without external dependencies (SDL3/OpenAL).
 */
class PHPGLFWAudioBackend implements AudioBackendInterface
{
    private GLAudioEngine $engine;

    /** @var array<string, GLSound> loaded sounds by path */
    private array $sounds = [];

    /** @var array<int, GLSound> active streams by handle */
    private array $streams = [];

    private int $nextHandle = 1;

    public function __construct()
    {
        if (!class_exists(GLAudioEngine::class)) {
            throw new \RuntimeException('GL\Audio\Engine not available — php-glfw was built without audio support.');
        }
        $this->engine = new GLAudioEngine([]);
        $this->engine->start();
    }

    public static function isAvailable(): bool
    {
        return class_exists(GLAudioEngine::class);
    }

    public function loadWav(string $path): AudioClipData
    {
        // AudioClipData is not used for actual playback in this backend,
        // but we still return a valid object to satisfy the interface.
        // The real sound is loaded lazily via getSound() when played.
        return new AudioClipData('', 44100, 2, 16, $path);
    }

    public function play(AudioClipData $clip, float $volume = 1.0): void
    {
        $sound = $this->getSound($clip->sourcePath);
        $sound->setVolume($volume);
        $sound->setLoop(false);
        $sound->play();
    }

    public function streamStart(AudioClipData $clip): int
    {
        $sound = $this->getSound($clip->sourcePath);
        $sound->setLoop(true);
        $sound->setVolume(1.0);
        $sound->play();

        $handle = $this->nextHandle++;
        $this->streams[$handle] = $sound;
        return $handle;
    }

    public function streamQueued(int $handle): int
    {
        $sound = $this->streams[$handle] ?? null;
        if ($sound === null || !$sound->isPlaying()) {
            return 0;
        }
        // Return a large value so AudioManager doesn't try to re-enqueue
        return PHP_INT_MAX;
    }

    public function streamEnqueue(int $handle, AudioClipData $clip): void
    {
        // Looping is handled natively by GL\Audio\Sound — nothing to do
    }

    public function streamSetVolume(int $handle, float $volume): void
    {
        $sound = $this->streams[$handle] ?? null;
        $sound?->setVolume($volume);
    }

    public function streamStop(int $handle): void
    {
        $sound = $this->streams[$handle] ?? null;
        $sound?->stop();
        unset($this->streams[$handle]);
    }

    public function shutdown(): void
    {
        foreach ($this->streams as $sound) {
            $sound->stop();
        }
        $this->streams = [];
        $this->sounds = [];
        $this->engine->stop();
    }

    public function getName(): string
    {
        return 'php-glfw (miniaudio)';
    }

    private function getSound(string $path): GLSound
    {
        if (!isset($this->sounds[$path])) {
            $this->sounds[$path] = $this->engine->soundFromDisk($path);
        }
        return $this->sounds[$path];
    }
}
