<?php

namespace VISU\Audio;

use FFI\CData;
use VISU\SDL3\SDL;

class AudioStream
{
    public function __construct(
        private SDL $sdl,
        private CData $stream,
    ) {}

    public function putData(CData $buffer, int $length): bool
    {
        return (bool) $this->sdl->ffi->SDL_PutAudioStreamData($this->stream, $buffer, $length);
    }

    public function resume(): bool
    {
        return (bool) $this->sdl->ffi->SDL_ResumeAudioStream($this->stream);
    }

    public function pause(): bool
    {
        return (bool) $this->sdl->ffi->SDL_PauseAudioStream($this->stream);
    }

    public function clear(): bool
    {
        return (bool) $this->sdl->ffi->SDL_ClearAudioStream($this->stream);
    }

    public function getQueued(): int
    {
        return $this->sdl->ffi->SDL_GetAudioStreamQueued($this->stream);
    }

    public function destroy(): void
    {
        $this->sdl->ffi->SDL_DestroyAudioStream($this->stream);
    }

    public function getNative(): CData
    {
        return $this->stream;
    }
}
