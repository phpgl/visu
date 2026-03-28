<?php

namespace VISU\Audio;

use VISU\Audio\Backend\PHPGLFWAudioBackend;

class AudioManager
{
    private AudioBackendInterface $backend;

    /** @var Mp3Decoder|null */
    private ?object $mp3Decoder = null;

    /**
     * Clip cache (path -> AudioClipData).
     *
     * @var array<string, AudioClipData>
     */
    private array $clipCache = [];

    /**
     * Per-channel volume (0.0 to 1.0).
     *
     * @var array<string, float>
     */
    private array $channelVolumes = [];

    /**
     * Currently playing music clip (for looping).
     */
    private ?AudioClipData $currentMusic = null;

    /**
     * Whether music is currently playing.
     */
    private bool $musicPlaying = false;

    /**
     * Stream handle for music playback.
     */
    private ?int $musicStreamHandle = null;

    /**
     * Create an AudioManager with explicit backend.
     */
    public function __construct(AudioBackendInterface $backend)
    {
        $this->backend = $backend;

        // Default volumes
        foreach (AudioChannel::cases() as $channel) {
            $this->channelVolumes[$channel->value] = 1.0;
        }
    }

    /**
     * Auto-detect the best available audio backend.
     * Priority: SDL3 (if SDL instance provided) -> OpenAL -> exception.
     */
    public static function create(mixed $sdl = null): self
    {
        // FFI-based backends (SDL3, OpenAL) require the FFI extension.
        // We use string class names to avoid autoloading classes with FFI typed properties.
        if (class_exists('FFI', false)) {
            // Try SDL3 first if an SDL instance is available
            if ($sdl !== null) {
                try {
                    /** @var class-string<AudioBackendInterface> */
                    $cls = 'VISU\\Audio\\Backend\\SDL3AudioBackend';
                    return new self(new $cls($sdl));
                } catch (\Throwable) {
                    // Fall through to OpenAL
                }
            }

            // Try OpenAL
            try {
                /** @var class-string<AudioBackendInterface> */
                $cls = 'VISU\\Audio\\Backend\\OpenALAudioBackend';
                if ($cls::isAvailable()) {
                    return new self(new $cls());
                }
            } catch (\Throwable) {
                // Fall through to php-glfw
            }
        }

        // Try php-glfw built-in audio (miniaudio)
        if (PHPGLFWAudioBackend::isAvailable()) {
            return new self(new PHPGLFWAudioBackend());
        }

        throw new \RuntimeException(
            'No audio backend available.'
        );
    }

    /**
     * Get the active backend name.
     */
    public function getBackendName(): string
    {
        return $this->backend->getName();
    }

    /**
     * Get the active backend instance.
     */
    public function getBackend(): AudioBackendInterface
    {
        return $this->backend;
    }

    /**
     * Load an audio file (WAV or MP3) and return AudioClipData. Results are cached.
     */
    public function loadClip(string $path): AudioClipData
    {
        if (isset($this->clipCache[$path])) {
            return $this->clipCache[$path];
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'mp3') {
            // php-glfw backend handles MP3 natively via soundFromDisk(),
            // so we only need the source path, not decoded PCM data.
            if ($this->backend instanceof PHPGLFWAudioBackend) {
                $clip = new AudioClipData('', 44100, 2, 16, $path);
            } elseif (class_exists('FFI', false)) {
                if ($this->mp3Decoder === null) {
                    $this->mp3Decoder = new Mp3Decoder();
                }
                $clip = $this->mp3Decoder->decode($path);
            } else {
                throw new \RuntimeException("MP3 decoding requires FFI extension: {$path}");
            }
        } else {
            $clip = $this->backend->loadWav($path);
        }

        $this->clipCache[$path] = $clip;
        return $clip;
    }

    /**
     * Play a sound effect (one-shot).
     */
    public function playSound(string $path, AudioChannel $channel = AudioChannel::SFX): void
    {
        $clip = $this->loadClip($path);
        $volume = $this->channelVolumes[$channel->value] ?? 1.0;
        $this->backend->play($clip, $volume);
    }

    /**
     * Start playing a music track. Stops any currently playing music.
     */
    public function playMusic(string $path): void
    {
        $this->stopMusic();
        $this->currentMusic = $this->loadClip($path);
        $this->musicPlaying = true;
        $this->musicStreamHandle = $this->backend->streamStart($this->currentMusic);
        $musicVol = $this->channelVolumes[AudioChannel::Music->value] ?? 1.0;
        $this->backend->streamSetVolume($this->musicStreamHandle, $musicVol);
    }

    /**
     * Stop the currently playing music.
     */
    public function stopMusic(): void
    {
        if ($this->musicStreamHandle !== null) {
            $this->backend->streamStop($this->musicStreamHandle);
            $this->musicStreamHandle = null;
        }
        $this->musicPlaying = false;
        $this->currentMusic = null;
    }

    /**
     * Check if music is currently playing.
     */
    public function isMusicPlaying(): bool
    {
        return $this->musicPlaying;
    }

    /**
     * Set volume for a specific channel (0.0 to 1.0).
     */
    public function setChannelVolume(AudioChannel $channel, float $volume): void
    {
        $this->channelVolumes[$channel->value] = max(0.0, min(1.0, $volume));

        // Apply live to music stream
        if ($channel === AudioChannel::Music && $this->musicStreamHandle !== null) {
            $this->backend->streamSetVolume($this->musicStreamHandle, $this->channelVolumes[$channel->value]);
        }
    }

    /**
     * Get volume for a specific channel.
     */
    public function getChannelVolume(AudioChannel $channel): float
    {
        return $this->channelVolumes[$channel->value] ?? 1.0;
    }

    /**
     * Play an AudioClipData directly.
     */
    public function play(AudioClipData $clip, float $volume = 1.0): void
    {
        $this->backend->play($clip, $volume);
    }

    /**
     * Call once per game loop tick.
     * Handles music looping when the stream buffer runs low.
     */
    public function update(): void
    {
        if ($this->musicPlaying && $this->currentMusic !== null && $this->musicStreamHandle !== null) {
            $queued = $this->backend->streamQueued($this->musicStreamHandle);
            if ($queued < $this->currentMusic->getByteLength() / 2) {
                $this->backend->streamEnqueue($this->musicStreamHandle, $this->currentMusic);
            }
        }
    }

    /**
     * Unload a cached clip.
     */
    public function unloadClip(string $path): void
    {
        unset($this->clipCache[$path]);
    }

    /**
     * Clear all cached clips.
     */
    public function clearCache(): void
    {
        $this->clipCache = [];
    }

    public function __destruct()
    {
        if ($this->musicStreamHandle !== null) {
            $this->backend->streamStop($this->musicStreamHandle);
        }
        $this->backend->shutdown();
    }
}
