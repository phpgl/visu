<?php

namespace VISU\Audio;

use VISU\Audio\Backend\OpenALAudioBackend;
use VISU\Audio\Backend\SDL3AudioBackend;
use VISU\SDL3\SDL;

class AudioManager
{
    private AudioBackendInterface $backend;

    private ?Mp3Decoder $mp3Decoder = null;

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
    public static function create(?SDL $sdl = null): self
    {
        // Try SDL3 first if an SDL instance is available
        if ($sdl !== null) {
            try {
                return new self(new SDL3AudioBackend($sdl));
            } catch (\Throwable) {
                // Fall through to OpenAL
            }
        }

        // Try OpenAL
        if (OpenALAudioBackend::isAvailable()) {
            try {
                return new self(new OpenALAudioBackend());
            } catch (\Throwable) {
                // Fall through to error
            }
        }

        throw new \RuntimeException(
            'No audio backend available. Install SDL3 (brew install sdl3) or OpenAL Soft (brew install openal-soft).'
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
            if ($this->mp3Decoder === null) {
                $this->mp3Decoder = new Mp3Decoder();
            }
            $clip = $this->mp3Decoder->decode($path);
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
