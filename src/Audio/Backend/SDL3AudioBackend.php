<?php

namespace VISU\Audio\Backend;

use FFI;
use VISU\Audio\AudioBackendInterface;
use VISU\Audio\AudioClipData;
use VISU\SDL3\Exception\SDLException;
use VISU\SDL3\SDL;

class SDL3AudioBackend implements AudioBackendInterface
{
    /**
     * The main playback stream for one-shot sounds.
     */
    private \FFI\CData $mainStream;

    /**
     * Additional streams for music/looping.
     * @var array<int, \FFI\CData>
     */
    private array $streams = [];

    private int $nextHandle = 1;

    public function __construct(
        private SDL $sdl,
        int $sampleRate = 44100,
        int $channels = 2,
    ) {
        $spec = $sdl->ffi->new('SDL_AudioSpec');
        $spec->format   = 0x8010; // SDL_AUDIO_S16
        $spec->channels = $channels;
        $spec->freq     = $sampleRate;

        $stream = $sdl->ffi->SDL_OpenAudioDeviceStream(
            SDL::AUDIO_DEVICE_DEFAULT_PLAYBACK,
            FFI::addr($spec),
            null,
            null
        );

        if ($stream === null) {
            throw new SDLException('SDL_OpenAudioDeviceStream failed: ' . $sdl->getError());
        }

        $this->mainStream = $stream;
        $sdl->ffi->SDL_ResumeAudioStream($stream);
    }

    public function loadWav(string $path): AudioClipData
    {
        $spec      = $this->sdl->ffi->new('SDL_AudioSpec');
        $audioBuf  = $this->sdl->ffi->new('uint8_t*');
        $audioLen  = $this->sdl->ffi->new('uint32_t');

        $ok = $this->sdl->ffi->SDL_LoadWAV(
            $path,
            FFI::addr($spec),
            FFI::addr($audioBuf),
            FFI::addr($audioLen)
        );

        if (!$ok) {
            throw new SDLException("SDL_LoadWAV failed for '{$path}': " . $this->sdl->getError());
        }

        $length = (int) $audioLen->cdata;
        $pcm = FFI::string($audioBuf, $length);

        // Free SDL-allocated buffer
        $this->sdl->ffi->SDL_free($audioBuf);

        $bitsPerSample = match ($spec->format) {
            0x8010 => 16, // SDL_AUDIO_S16
            0x8008 => 8,  // SDL_AUDIO_S8
            0x8020 => 32, // SDL_AUDIO_S32
            default => 16,
        };

        return new AudioClipData($pcm, $spec->freq, $spec->channels, $bitsPerSample, $path);
    }

    public function play(AudioClipData $clip, float $volume = 1.0): void
    {
        $data = $clip->pcmData;
        $len = strlen($data);

        $buf = FFI::new("uint8_t[$len]");
        FFI::memcpy($buf, $data, $len);

        $this->sdl->ffi->SDL_PutAudioStreamData($this->mainStream, $buf, $len);
    }

    public function streamStart(AudioClipData $clip): int
    {
        $spec = $this->sdl->ffi->new('SDL_AudioSpec');
        $spec->format   = 0x8010;
        $spec->channels = $clip->channels;
        $spec->freq     = $clip->sampleRate;

        $stream = $this->sdl->ffi->SDL_OpenAudioDeviceStream(
            SDL::AUDIO_DEVICE_DEFAULT_PLAYBACK,
            FFI::addr($spec),
            null,
            null
        );

        if ($stream === null) {
            throw new SDLException('SDL_OpenAudioDeviceStream failed: ' . $this->sdl->getError());
        }

        $this->sdl->ffi->SDL_ResumeAudioStream($stream);

        $handle = $this->nextHandle++;
        $this->streams[$handle] = $stream;

        // Enqueue initial data
        $this->streamEnqueue($handle, $clip);

        return $handle;
    }

    public function streamQueued(int $handle): int
    {
        if (!isset($this->streams[$handle])) {
            return 0;
        }
        return $this->sdl->ffi->SDL_GetAudioStreamQueued($this->streams[$handle]);
    }

    public function streamEnqueue(int $handle, AudioClipData $clip): void
    {
        if (!isset($this->streams[$handle])) {
            return;
        }

        $data = $clip->pcmData;
        $len = strlen($data);
        $buf = FFI::new("uint8_t[$len]");
        FFI::memcpy($buf, $data, $len);

        $this->sdl->ffi->SDL_PutAudioStreamData($this->streams[$handle], $buf, $len);
    }

    public function streamSetVolume(int $handle, float $volume): void
    {
        // SDL3 stream API does not support per-stream volume natively
    }

    public function streamStop(int $handle): void
    {
        if (!isset($this->streams[$handle])) {
            return;
        }

        $this->sdl->ffi->SDL_ClearAudioStream($this->streams[$handle]);
        $this->sdl->ffi->SDL_DestroyAudioStream($this->streams[$handle]);
        unset($this->streams[$handle]);
    }

    public function shutdown(): void
    {
        foreach (array_keys($this->streams) as $handle) {
            $this->streamStop($handle);
        }

        $this->sdl->ffi->SDL_DestroyAudioStream($this->mainStream);
    }

    public function getName(): string
    {
        return 'SDL3';
    }

    public static function isAvailable(): bool
    {
        return class_exists('FFI', false) && class_exists(SDL::class);
    }
}
