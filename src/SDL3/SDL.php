<?php

namespace VISU\SDL3;

use FFI;
use VISU\SDL3\Exception\SDLException;

class SDL
{
    public const INIT_AUDIO   = 0x00000010;
    public const INIT_GAMEPAD = 0x00002000;
    public const INIT_EVENTS  = 0x00004000;

    // SDL3 event type constants
    public const EVENT_GAMEPAD_AXIS_MOTION = 0x650;
    public const EVENT_GAMEPAD_BUTTON_DOWN = 0x651;
    public const EVENT_GAMEPAD_BUTTON_UP   = 0x652;
    public const EVENT_GAMEPAD_ADDED       = 0x653;
    public const EVENT_GAMEPAD_REMOVED     = 0x654;

    // SDL_AUDIO_DEVICE_DEFAULT_PLAYBACK
    public const AUDIO_DEVICE_DEFAULT_PLAYBACK = 0xFFFFFFFF;

    private static ?self $instance = null;

    public readonly FFI $ffi;

    private function __construct()
    {
        $declarations = <<<'CDEF'
typedef unsigned char Uint8;
typedef signed short Sint16;
typedef unsigned int Uint32;
typedef unsigned long long Uint64;
typedef int SDL_JoystickID;
typedef Uint32 SDL_AudioDeviceID;
typedef struct SDL_Gamepad SDL_Gamepad;
typedef struct SDL_AudioStream SDL_AudioStream;

typedef struct {
    int format;
    int channels;
    int freq;
} SDL_AudioSpec;

typedef union SDL_Event {
    Uint32 type;
    struct {
        Uint32 type;
        Uint32 reserved;
        Uint64 timestamp;
        int which;
        Uint8 axis;
        Uint8 p1;
        Uint8 p2;
        Uint8 p3;
        Sint16 value;
        Uint16 p4;
    } gaxis;
    struct {
        Uint32 type;
        Uint32 reserved;
        Uint64 timestamp;
        int which;
        Uint8 button;
        bool down;
        Uint8 p1;
        Uint8 p2;
    } gbutton;
    struct {
        Uint32 type;
        Uint32 reserved;
        Uint64 timestamp;
        int which;
    } gdevice;
    Uint8 padding[128];
} SDL_Event;

bool SDL_Init(Uint32 flags);
bool SDL_InitSubSystem(Uint32 flags);
void SDL_QuitSubSystem(Uint32 flags);
void SDL_Quit(void);
const char* SDL_GetError(void);

bool SDL_PollEvent(SDL_Event *event);
void SDL_PumpEvents(void);

SDL_AudioStream* SDL_OpenAudioDeviceStream(Uint32 devid, const SDL_AudioSpec *spec, void *callback, void *userdata);
bool SDL_PutAudioStreamData(SDL_AudioStream *stream, const void *buf, int len);
bool SDL_ResumeAudioStream(SDL_AudioStream *stream);
bool SDL_PauseAudioStream(SDL_AudioStream *stream);
bool SDL_ClearAudioStream(SDL_AudioStream *stream);
void SDL_DestroyAudioStream(SDL_AudioStream *stream);
int SDL_GetAudioStreamQueued(SDL_AudioStream *stream);
bool SDL_LoadWAV(const char *path, SDL_AudioSpec *spec, Uint8 **audio_buf, Uint32 *audio_len);
void SDL_free(void *mem);

bool SDL_HasGamepad(void);
int* SDL_GetGamepads(int *count);
SDL_Gamepad* SDL_OpenGamepad(int instance_id);
void SDL_CloseGamepad(SDL_Gamepad *gamepad);
bool SDL_GamepadConnected(SDL_Gamepad *gamepad);
int SDL_GetGamepadInstanceID(SDL_Gamepad *gamepad);
const char* SDL_GetGamepadName(SDL_Gamepad *gamepad);
Sint16 SDL_GetGamepadAxis(SDL_Gamepad *gamepad, int axis);
bool SDL_GetGamepadButton(SDL_Gamepad *gamepad, int button);
CDEF;

        $libPath = $this->findLibrary();
        $this->ffi = FFI::cdef($declarations, $libPath);
    }

    private function findLibrary(): string
    {
        $candidates = [
            // macOS – Homebrew (linked)
            '/opt/homebrew/lib/libSDL3.dylib',
            '/usr/local/lib/libSDL3.dylib',
            // Linux
            '/usr/lib/libSDL3.so',
            '/usr/lib/x86_64-linux-gnu/libSDL3.so',
            '/usr/lib/aarch64-linux-gnu/libSDL3.so',
            '/usr/local/lib/libSDL3.so',
        ];

        // macOS – Homebrew keg-only or specific Cellar versions
        if (PHP_OS_FAMILY === 'Darwin') {
            foreach (['/opt/homebrew/opt/sdl3/lib', '/usr/local/opt/sdl3/lib'] as $kegDir) {
                if (is_dir($kegDir)) {
                    $candidates[] = $kegDir . '/libSDL3.dylib';
                }
            }
            foreach (['/opt/homebrew/Cellar/sdl3', '/usr/local/Cellar/sdl3'] as $cellarDir) {
                if (is_dir($cellarDir)) {
                    $versions = @scandir($cellarDir, SCANDIR_SORT_DESCENDING);
                    if ($versions) {
                        foreach ($versions as $ver) {
                            if ($ver[0] === '.') continue;
                            $candidates[] = $cellarDir . '/' . $ver . '/lib/libSDL3.dylib';
                        }
                    }
                }
            }
        }

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Last resort: let the dynamic linker try
        $fallbacks = PHP_OS_FAMILY === 'Windows'
            ? ['SDL3.dll']
            : ['libSDL3.so', 'libSDL3.dylib'];

        foreach ($fallbacks as $name) {
            try {
                \FFI::cdef('int SDL_GetVersion(void);', $name);
                return $name;
            } catch (\FFI\Exception) {
                continue;
            }
        }

        throw new SDLException('SDL3 shared library not found. Install SDL3 (e.g. brew install sdl3).');
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(int $flags): void
    {
        if (!$this->ffi->SDL_Init($flags)) {
            throw new SDLException('SDL_Init failed: ' . $this->getError());
        }
    }

    public function initSubSystem(int $flags): void
    {
        if (!$this->ffi->SDL_InitSubSystem($flags)) {
            throw new SDLException('SDL_InitSubSystem failed: ' . $this->getError());
        }
    }

    public function quitSubSystem(int $flags): void
    {
        $this->ffi->SDL_QuitSubSystem($flags);
    }

    public function quit(): void
    {
        $this->ffi->SDL_Quit();
    }

    public function getError(): string
    {
        return FFI::string($this->ffi->SDL_GetError());
    }

    /**
     * Poll for pending SDL events.
     * Returns an associative array describing the event, or null if the queue is empty.
     *
     * @return array<string, mixed>|null
     */
    public function pollEvent(): ?array
    {
        $event = $this->ffi->new('SDL_Event');
        if (!$this->ffi->SDL_PollEvent(FFI::addr($event))) {
            return null;
        }

        $type = $event->type;
        $result = ['type' => $type];

        switch ($type) {
            case self::EVENT_GAMEPAD_AXIS_MOTION:
                $result['which']  = $event->gaxis->which;
                $result['axis']   = $event->gaxis->axis;
                $result['value']  = $event->gaxis->value;
                break;

            case self::EVENT_GAMEPAD_BUTTON_DOWN:
            case self::EVENT_GAMEPAD_BUTTON_UP:
                $result['which']  = $event->gbutton->which;
                $result['button'] = $event->gbutton->button;
                $result['down']   = $event->gbutton->down;
                break;

            case self::EVENT_GAMEPAD_ADDED:
            case self::EVENT_GAMEPAD_REMOVED:
                $result['which'] = $event->gdevice->which;
                break;
        }

        return $result;
    }

    public function pumpEvents(): void
    {
        $this->ffi->SDL_PumpEvents();
    }
}
