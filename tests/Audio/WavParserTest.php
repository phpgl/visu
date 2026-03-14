<?php

namespace VISU\Tests\Audio;

use PHPUnit\Framework\TestCase;
use VISU\Audio\Backend\OpenALAudioBackend;

class WavParserTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/visu_wav_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*') ?: []);
        rmdir($this->tempDir);
    }

    private function createWavFile(int $sampleRate = 44100, int $channels = 2, int $bitsPerSample = 16, int $dataBytes = 1024): string
    {
        $byteRate = $sampleRate * $channels * ($bitsPerSample / 8);
        $blockAlign = $channels * ($bitsPerSample / 8);
        $pcmData = str_repeat("\x00", $dataBytes);

        $fmt = pack('vvVVvv',
            1,               // PCM format
            $channels,
            $sampleRate,
            (int) $byteRate,
            (int) $blockAlign,
            $bitsPerSample
        );

        $fmtChunk = 'fmt ' . pack('V', strlen($fmt)) . $fmt;
        $dataChunk = 'data' . pack('V', strlen($pcmData)) . $pcmData;

        $riff = 'RIFF' . pack('V', 4 + strlen($fmtChunk) + strlen($dataChunk)) . 'WAVE' . $fmtChunk . $dataChunk;

        $path = $this->tempDir . '/test.wav';
        file_put_contents($path, $riff);
        return $path;
    }

    public function testParseValidWav(): void
    {
        $path = $this->createWavFile(44100, 2, 16, 1024);

        // Use reflection to test the private parseWavFile method
        $method = new \ReflectionMethod(OpenALAudioBackend::class, 'parseWavFile');
        $method->setAccessible(true);

        $clip = $method->invoke(null, $path);

        $this->assertSame(44100, $clip->sampleRate);
        $this->assertSame(2, $clip->channels);
        $this->assertSame(16, $clip->bitsPerSample);
        $this->assertSame(1024, $clip->getByteLength());
        $this->assertSame($path, $clip->sourcePath);
    }

    public function testParseMono8bit(): void
    {
        $path = $this->createWavFile(22050, 1, 8, 512);

        $method = new \ReflectionMethod(OpenALAudioBackend::class, 'parseWavFile');
        $method->setAccessible(true);

        $clip = $method->invoke(null, $path);

        $this->assertSame(22050, $clip->sampleRate);
        $this->assertSame(1, $clip->channels);
        $this->assertSame(8, $clip->bitsPerSample);
        $this->assertSame(512, $clip->getByteLength());
    }

    public function testRejectsNonWavFile(): void
    {
        $path = $this->tempDir . '/bad.wav';
        file_put_contents($path, 'NOT A WAV FILE AT ALL WITH ENOUGH BYTES TO PASS SIZE CHECK!!!!');

        $method = new \ReflectionMethod(OpenALAudioBackend::class, 'parseWavFile');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not a valid WAV file');
        $method->invoke(null, $path);
    }

    public function testRejectsTooSmallFile(): void
    {
        $path = $this->tempDir . '/tiny.wav';
        file_put_contents($path, 'RIFF');

        $method = new \ReflectionMethod(OpenALAudioBackend::class, 'parseWavFile');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WAV file too small');
        $method->invoke(null, $path);
    }
}
