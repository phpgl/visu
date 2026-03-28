<?php

namespace VISU\Audio\Backend;

use FFI;
use VISU\Audio\AudioBackendInterface;
use VISU\Audio\AudioClipData;

class OpenALAudioBackend implements AudioBackendInterface
{
    private FFI $al;

    /** @var \FFI\CData OpenAL device pointer */
    private \FFI\CData $device;

    /** @var \FFI\CData OpenAL context pointer */
    private \FFI\CData $context;

    // OpenAL constants
    private const AL_FORMAT_MONO8    = 0x1100;
    private const AL_FORMAT_MONO16   = 0x1101;
    private const AL_FORMAT_STEREO8  = 0x1102;
    private const AL_FORMAT_STEREO16 = 0x1103;

    private const AL_SOURCE_STATE = 0x1010;
    private const AL_PLAYING      = 0x1012;
    private const AL_BUFFERS_QUEUED    = 0x1015;
    private const AL_BUFFERS_PROCESSED = 0x1016;
    private const AL_GAIN    = 0x100A;
    private const AL_LOOPING = 0x1007;
    private const AL_BUFFER  = 0x1009;

    /** @phpstan-ignore-next-line */
    private const AL_TRUE  = 1;
    private const AL_FALSE = 0;

    /**
     * Pool of sources for one-shot playback.
     * @var \FFI\CData[]
     */
    private array $sfxSources = [];
    private int $sfxSourceCount = 32;
    private int $sfxSourceIndex = 0;

    /**
     * Streaming handles: handle -> {source, buffers[], clip}
     * @var array<int, array{source: int, buffers: int[], clip: AudioClipData, chunkSize: int}>
     */
    private array $streams = [];
    private int $nextHandle = 1;

    /**
     * Tracks OpenAL buffer IDs to free them later.
     * @var int[]
     */
    private array $allocatedBuffers = [];

    public function __construct()
    {
        $this->al = $this->createFFI();

        // Open default device
        $this->device = $this->al->alcOpenDevice(null);
        if (FFI::isNull($this->device)) {
            throw new \RuntimeException('OpenAL: Failed to open default audio device');
        }

        // Create and activate context
        $this->context = $this->al->alcCreateContext($this->device, null);
        if (FFI::isNull($this->context)) {
            $this->al->alcCloseDevice($this->device);
            throw new \RuntimeException('OpenAL: Failed to create audio context');
        }

        $this->al->alcMakeContextCurrent($this->context);

        // Pre-allocate SFX source pool
        $this->initSfxPool();
    }

    private function createFFI(): FFI
    {
        $declarations = <<<'CDEF'
typedef unsigned int ALuint;
typedef int ALint;
typedef int ALsizei;
typedef int ALenum;
typedef float ALfloat;
typedef char ALchar;
typedef void ALvoid;
typedef unsigned char ALboolean;

typedef void ALCdevice;
typedef void ALCcontext;
typedef int ALCenum;
typedef int ALCint;

// Device & Context
ALCdevice* alcOpenDevice(const ALchar *devicename);
ALCcontext* alcCreateContext(ALCdevice *device, const ALCint *attrlist);
ALboolean alcMakeContextCurrent(ALCcontext *context);
void alcDestroyContext(ALCcontext *context);
ALboolean alcCloseDevice(ALCdevice *device);

// Buffers
void alGenBuffers(ALsizei n, ALuint *buffers);
void alDeleteBuffers(ALsizei n, const ALuint *buffers);
void alBufferData(ALuint buffer, ALenum format, const ALvoid *data, ALsizei size, ALsizei freq);

// Sources
void alGenSources(ALsizei n, ALuint *sources);
void alDeleteSources(ALsizei n, const ALuint *sources);
void alSourcei(ALuint source, ALenum param, ALint value);
void alSourcef(ALuint source, ALenum param, ALfloat value);
void alSourcePlay(ALuint source);
void alSourceStop(ALuint source);
void alGetSourcei(ALuint source, ALenum param, ALint *value);

// Streaming (buffer queue)
void alSourceQueueBuffers(ALuint source, ALsizei nb, const ALuint *buffers);
void alSourceUnqueueBuffers(ALuint source, ALsizei nb, ALuint *buffers);

// Error
ALenum alGetError(void);
CDEF;

        $libPath = self::findLibrary();
        return FFI::cdef($declarations, $libPath);
    }

    private static function findLibrary(): string
    {
        $candidates = [
            // macOS – system framework
            '/System/Library/Frameworks/OpenAL.framework/OpenAL',
            // macOS – Homebrew (linked)
            '/opt/homebrew/lib/libopenal.dylib',
            '/usr/local/lib/libopenal.dylib',
            // Linux
            '/usr/lib/x86_64-linux-gnu/libopenal.so.1',
            '/usr/lib/x86_64-linux-gnu/libopenal.so',
            '/usr/lib/aarch64-linux-gnu/libopenal.so.1',
            '/usr/lib/aarch64-linux-gnu/libopenal.so',
            '/usr/lib/libopenal.so.1',
            '/usr/lib/libopenal.so',
        ];

        // macOS – Homebrew keg-only (openal-soft is not symlinked by default)
        if (PHP_OS_FAMILY === 'Darwin') {
            foreach (['/opt/homebrew/opt/openal-soft/lib', '/usr/local/opt/openal-soft/lib'] as $kegDir) {
                if (is_dir($kegDir)) {
                    $candidates[] = $kegDir . '/libopenal.dylib';
                }
            }
            // Scan Homebrew Cellar for any installed version
            foreach (['/opt/homebrew/Cellar/openal-soft', '/usr/local/Cellar/openal-soft'] as $cellarDir) {
                if (is_dir($cellarDir)) {
                    $versions = @scandir($cellarDir, SCANDIR_SORT_DESCENDING);
                    if ($versions) {
                        foreach ($versions as $ver) {
                            if ($ver[0] === '.') continue;
                            $candidates[] = $cellarDir . '/' . $ver . '/lib/libopenal.dylib';
                        }
                    }
                }
            }
        }

        // Linux – scan common lib directories for any libopenal variant
        if (PHP_OS_FAMILY === 'Linux') {
            foreach (['/usr/lib', '/usr/local/lib'] as $libDir) {
                $matches = @glob($libDir . '/*/libopenal.so*');
                if ($matches) {
                    array_push($candidates, ...$matches);
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
            ? ['OpenAL32.dll', 'soft_oal.dll']
            : ['libopenal.so.1', 'libopenal.so', 'libopenal.dylib'];

        foreach ($fallbacks as $name) {
            try {
                \FFI::cdef('void alGetError(void);', $name);
                return $name;
            } catch (\FFI\Exception) {
                continue;
            }
        }

        throw new \RuntimeException(
            'OpenAL shared library not found. Install OpenAL Soft (e.g. brew install openal-soft, apt install libopenal-dev).'
        );
    }

    /**
     * Detect whether OpenAL is available on this system without fully initializing.
     */
    public static function isAvailable(): bool
    {
        try {
            self::findLibrary();
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    private function initSfxPool(): void
    {
        $sources = $this->al->new("ALuint[{$this->sfxSourceCount}]");
        $this->al->alGenSources($this->sfxSourceCount, $sources);

        for ($i = 0; $i < $this->sfxSourceCount; $i++) {
            $this->sfxSources[$i] = $sources[$i];
        }
    }

    private function getALFormat(AudioClipData $clip): int
    {
        if ($clip->channels === 1) {
            return $clip->bitsPerSample === 8 ? self::AL_FORMAT_MONO8 : self::AL_FORMAT_MONO16;
        }
        return $clip->bitsPerSample === 8 ? self::AL_FORMAT_STEREO8 : self::AL_FORMAT_STEREO16;
    }

    public function loadWav(string $path): AudioClipData
    {
        return self::parseWavFile($path);
    }

    /**
     * Parse a WAV file into AudioClipData (pure PHP, no SDL dependency).
     */
    private static function parseWavFile(string $path): AudioClipData
    {
        $data = file_get_contents($path);
        if ($data === false) {
            throw new \RuntimeException("Failed to read WAV file: {$path}");
        }

        if (strlen($data) < 44) {
            throw new \RuntimeException("WAV file too small: {$path}");
        }

        // RIFF header
        $riff = substr($data, 0, 4);
        $wave = substr($data, 8, 4);
        if ($riff !== 'RIFF' || $wave !== 'WAVE') {
            throw new \RuntimeException("Not a valid WAV file: {$path}");
        }

        // Find fmt chunk
        $offset = 12;
        $fmtFound = false;
        $channels = 0;
        $sampleRate = 0;
        $bitsPerSample = 0;

        while ($offset < strlen($data) - 8) {
            $chunkId = substr($data, $offset, 4);
            $unpacked = unpack('V', substr($data, $offset + 4, 4));
            $chunkSize = $unpacked !== false ? $unpacked[1] : 0;

            if ($chunkId === 'fmt ') {
                $fmt = unpack('vAudioFormat/vChannels/VSampleRate/VByteRate/vBlockAlign/vBitsPerSample', substr($data, $offset + 8, 16));
                if ($fmt === false) {
                    throw new \RuntimeException('Failed to parse WAV fmt chunk');
                }
                $channels = $fmt['Channels'];
                $sampleRate = $fmt['SampleRate'];
                $bitsPerSample = $fmt['BitsPerSample'];
                $fmtFound = true;
            }

            if ($chunkId === 'data') {
                if (!$fmtFound) {
                    throw new \RuntimeException("WAV fmt chunk not found before data: {$path}");
                }
                $pcm = substr($data, $offset + 8, $chunkSize);
                return new AudioClipData($pcm, $sampleRate, $channels, $bitsPerSample, $path);
            }

            $offset += 8 + $chunkSize;
            // Chunks are padded to even size
            if ($chunkSize % 2 !== 0) {
                $offset++;
            }
        }

        throw new \RuntimeException("WAV data chunk not found: {$path}");
    }

    public function play(AudioClipData $clip, float $volume = 1.0): void
    {
        // Round-robin through SFX source pool
        $sourceId = $this->sfxSources[$this->sfxSourceIndex % $this->sfxSourceCount];
        $this->sfxSourceIndex++;

        // Stop if currently playing
        $this->al->alSourceStop($sourceId);

        // Create buffer and upload PCM data
        $bufId = $this->al->new('ALuint');
        $this->al->alGenBuffers(1, FFI::addr($bufId));
        $bufIdVal = $bufId->cdata;
        $this->allocatedBuffers[] = $bufIdVal;

        $format = $this->getALFormat($clip);
        $len = $clip->getByteLength();
        $pcmData = $clip->pcmData;
        $pcmBuf = $this->al->new("uint8_t[$len]");
        FFI::memcpy($pcmBuf, $pcmData, $len);

        $this->al->alBufferData($bufIdVal, $format, $pcmBuf, $len, $clip->sampleRate);

        $this->al->alSourcei($sourceId, self::AL_BUFFER, $bufIdVal);
        $this->al->alSourcef($sourceId, self::AL_GAIN, $volume);
        $this->al->alSourcei($sourceId, self::AL_LOOPING, self::AL_FALSE);
        $this->al->alSourcePlay($sourceId);
    }

    public function streamStart(AudioClipData $clip): int
    {
        // Create a dedicated source for streaming
        $srcId = $this->al->new('ALuint');
        $this->al->alGenSources(1, FFI::addr($srcId));
        $sourceId = $srcId->cdata;

        $format = $this->getALFormat($clip);

        // Split clip into streaming buffers (4 x quarter)
        $totalLen = $clip->getByteLength();
        $numBuffers = 4;
        $chunkSize = (int) ceil($totalLen / $numBuffers);

        // Align chunk to frame boundary
        $frameSize = $clip->channels * ($clip->bitsPerSample / 8);
        $chunkSize = (int)(floor($chunkSize / $frameSize) * $frameSize);
        if ($chunkSize < $frameSize) {
            $chunkSize = (int) $frameSize;
        }

        $bufferIds = [];
        for ($i = 0; $i < $numBuffers; $i++) {
            $offset = $i * $chunkSize;
            $remaining = $totalLen - $offset;
            if ($remaining <= 0) break;
            $size = min($chunkSize, $remaining);

            $bufId = $this->al->new('ALuint');
            $this->al->alGenBuffers(1, FFI::addr($bufId));
            $bufIdVal = $bufId->cdata;
            $bufferIds[] = $bufIdVal;

            $chunk = substr($clip->pcmData, $offset, $size);
            $pcmBuf = $this->al->new("uint8_t[$size]");
            FFI::memcpy($pcmBuf, $chunk, $size);

            $this->al->alBufferData($bufIdVal, $format, $pcmBuf, $size, $clip->sampleRate);

            $alBuf = $this->al->new('ALuint');
            $alBuf->cdata = $bufIdVal;
            $this->al->alSourceQueueBuffers($sourceId, 1, FFI::addr($alBuf));
        }

        $this->al->alSourcePlay($sourceId);

        $handle = $this->nextHandle++;
        $this->streams[$handle] = [
            'source' => $sourceId,
            'buffers' => $bufferIds,
            'clip' => $clip,
            'chunkSize' => $chunkSize,
        ];

        return $handle;
    }

    public function streamQueued(int $handle): int
    {
        if (!isset($this->streams[$handle])) {
            return 0;
        }

        $sourceId = $this->streams[$handle]['source'];
        $queued = $this->al->new('ALint');
        $this->al->alGetSourcei($sourceId, self::AL_BUFFERS_QUEUED, FFI::addr($queued));

        $processed = $this->al->new('ALint');
        $this->al->alGetSourcei($sourceId, self::AL_BUFFERS_PROCESSED, FFI::addr($processed));

        $remaining = (int)$queued->cdata - (int)$processed->cdata;
        return $remaining * $this->streams[$handle]['chunkSize'];
    }

    public function streamEnqueue(int $handle, AudioClipData $clip): void
    {
        if (!isset($this->streams[$handle])) {
            return;
        }

        $stream = &$this->streams[$handle];
        $sourceId = $stream['source'];
        $format = $this->getALFormat($clip);

        // Unqueue processed buffers first
        $processed = $this->al->new('ALint');
        $this->al->alGetSourcei($sourceId, self::AL_BUFFERS_PROCESSED, FFI::addr($processed));

        for ($i = 0; $i < $processed->cdata; $i++) {
            $unqueued = $this->al->new('ALuint');
            $this->al->alSourceUnqueueBuffers($sourceId, 1, FFI::addr($unqueued));
            $this->al->alDeleteBuffers(1, FFI::addr($unqueued));
        }

        // Queue new data in chunks
        $totalLen = $clip->getByteLength();
        $chunkSize = $stream['chunkSize'];
        $numChunks = (int) ceil($totalLen / $chunkSize);

        $newBuffers = [];
        for ($i = 0; $i < $numChunks; $i++) {
            $offset = $i * $chunkSize;
            $remaining = $totalLen - $offset;
            if ($remaining <= 0) break;
            $size = min($chunkSize, $remaining);

            $bufId = $this->al->new('ALuint');
            $this->al->alGenBuffers(1, FFI::addr($bufId));
            $bufIdVal = $bufId->cdata;
            $newBuffers[] = $bufIdVal;

            $chunk = substr($clip->pcmData, $offset, $size);
            $pcmBuf = $this->al->new("uint8_t[$size]");
            FFI::memcpy($pcmBuf, $chunk, $size);

            $this->al->alBufferData($bufIdVal, $format, $pcmBuf, $size, $clip->sampleRate);

            $alBuf = $this->al->new('ALuint');
            $alBuf->cdata = $bufIdVal;
            $this->al->alSourceQueueBuffers($sourceId, 1, FFI::addr($alBuf));
        }

        $stream['buffers'] = array_merge($stream['buffers'], $newBuffers);

        // Resume if stopped
        $state = $this->al->new('ALint');
        $this->al->alGetSourcei($sourceId, self::AL_SOURCE_STATE, FFI::addr($state));
        if ($state->cdata !== self::AL_PLAYING) {
            $this->al->alSourcePlay($sourceId);
        }
    }

    public function streamSetVolume(int $handle, float $volume): void
    {
        if (!isset($this->streams[$handle])) return;
        $this->al->alSourcef($this->streams[$handle]['source'], self::AL_GAIN, $volume);
    }

    public function streamStop(int $handle): void
    {
        if (!isset($this->streams[$handle])) {
            return;
        }

        $stream = $this->streams[$handle];
        $sourceId = $stream['source'];

        $this->al->alSourceStop($sourceId);

        // Unqueue all buffers
        $queued = $this->al->new('ALint');
        $this->al->alGetSourcei($sourceId, self::AL_BUFFERS_QUEUED, FFI::addr($queued));
        for ($i = 0; $i < $queued->cdata; $i++) {
            $buf = $this->al->new('ALuint');
            $this->al->alSourceUnqueueBuffers($sourceId, 1, FFI::addr($buf));
            $this->al->alDeleteBuffers(1, FFI::addr($buf));
        }

        $srcBuf = $this->al->new('ALuint');
        $srcBuf->cdata = $sourceId;
        $this->al->alDeleteSources(1, FFI::addr($srcBuf));

        unset($this->streams[$handle]);
    }

    public function shutdown(): void
    {
        // Stop all streams
        foreach (array_keys($this->streams) as $handle) {
            $this->streamStop($handle);
        }

        // Clean up SFX sources
        if (count($this->sfxSources) > 0) {
            $sources = $this->al->new("ALuint[{$this->sfxSourceCount}]");
            for ($i = 0; $i < $this->sfxSourceCount; $i++) {
                $sources[$i] = $this->sfxSources[$i];
            }
            $this->al->alDeleteSources($this->sfxSourceCount, $sources);
        }

        // Clean up allocated buffers
        foreach ($this->allocatedBuffers as $bufId) {
            $buf = $this->al->new('ALuint');
            $buf->cdata = $bufId;
            $this->al->alDeleteBuffers(1, FFI::addr($buf));
        }

        $this->al->alcMakeContextCurrent(null);
        $this->al->alcDestroyContext($this->context);
        $this->al->alcCloseDevice($this->device);
    }

    public function getName(): string
    {
        return 'OpenAL';
    }
}
