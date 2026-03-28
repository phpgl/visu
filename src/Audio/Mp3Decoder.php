<?php

namespace VISU\Audio;

use FFI;

class Mp3Decoder
{
    private FFI $ffi;

    public function __construct(?string $libPath = null)
    {
        if ($libPath === null) {
            $libPath = self::findLibrary();
        }

        $this->ffi = FFI::cdef(<<<'CDEF'
typedef struct {
    short *pcm;
    int samples;
    int channels;
    int sample_rate;
    int error;
} mp3_result_t;

void mp3_decode_buffer(const unsigned char *data, int data_size, mp3_result_t *result);
void mp3_free(void *ptr);
CDEF, $libPath);
    }

    /**
     * Decode an MP3 file into AudioClipData (16-bit PCM).
     */
    public function decode(string $path): AudioClipData
    {
        $data = @file_get_contents($path);
        if ($data === false) {
            throw new \RuntimeException("Failed to read MP3 file: {$path}");
        }

        return $this->decodeBuffer($data, $path);
    }

    /**
     * Decode MP3 data from a string buffer into AudioClipData.
     */
    public function decodeBuffer(string $data, string $sourcePath = '<buffer>'): AudioClipData
    {
        $len = strlen($data);
        if ($len === 0) {
            throw new \RuntimeException("Empty MP3 data for: {$sourcePath}");
        }

        $inputBuf = FFI::new("unsigned char[{$len}]");
        FFI::memcpy($inputBuf, $data, $len);

        $result = $this->ffi->new('mp3_result_t');
        $this->ffi->mp3_decode_buffer($inputBuf, $len, FFI::addr($result));

        if ($result->error !== 0) {
            throw new \RuntimeException("minimp3 decode error for: {$sourcePath}");
        }

        if ($result->samples === 0 || FFI::isNull($result->pcm)) {
            throw new \RuntimeException("No audio frames decoded from: {$sourcePath}");
        }

        $totalSamples = $result->samples * $result->channels;
        $byteLength = $totalSamples * 2; // 16-bit = 2 bytes per sample
        $pcm = FFI::string($result->pcm, $byteLength);

        $clipData = new AudioClipData(
            pcmData: $pcm,
            sampleRate: $result->sample_rate,
            channels: $result->channels,
            bitsPerSample: 16,
            sourcePath: $sourcePath,
        );

        $this->ffi->mp3_free($result->pcm);

        return $clipData;
    }

    private static function findLibrary(): string
    {
        $platformDir = self::getPlatformDir();
        $filename = PHP_OS_FAMILY === 'Windows' ? 'minimp3.dll'
            : (PHP_OS_FAMILY === 'Darwin' ? 'libminimp3.dylib' : 'libminimp3.so');

        $candidates = [];

        // Relative to VISU_PATH_ROOT if defined
        if (defined('VISU_PATH_ROOT')) {
            $root = VISU_PATH_ROOT;
            $candidates[] = "{$root}/resources/lib/minimp3/{$platformDir}/{$filename}";
        }

        // Relative to this file
        $dir = dirname(__DIR__, 2) . '/resources/lib/minimp3';
        $candidates[] = "{$dir}/{$platformDir}/{$filename}";

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        throw new \RuntimeException(
            "minimp3 shared library not found for {$platformDir}. Run: resources/lib/minimp3/build.sh"
        );
    }

    private static function getPlatformDir(): string
    {
        $arch = php_uname('m');

        return match (PHP_OS_FAMILY) {
            'Darwin' => $arch === 'x86_64' ? 'darwin-x86_64' : 'darwin-arm64',
            'Windows' => 'windows-x86_64',
            default => 'linux-x86_64', // Linux and others
        };
    }
}
