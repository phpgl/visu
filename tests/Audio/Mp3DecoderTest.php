<?php

namespace VISU\Tests\Audio;

use PHPUnit\Framework\TestCase;
use VISU\Audio\Mp3Decoder;

class Mp3DecoderTest extends TestCase
{
    private static ?Mp3Decoder $decoder = null;

    public static function setUpBeforeClass(): void
    {
        try {
            self::$decoder = new Mp3Decoder();
        } catch (\RuntimeException $e) {
            self::markTestSkipped('minimp3 library not available: ' . $e->getMessage());
        }
    }

    private function getFixturePath(string $name): string
    {
        return __DIR__ . '/fixtures/' . $name;
    }

    public function testDecodeSilenceMp3(): void
    {
        $clip = self::$decoder->decode($this->getFixturePath('silence.mp3'));

        $this->assertGreaterThan(0, $clip->sampleRate);
        $this->assertGreaterThan(0, $clip->channels);
        $this->assertSame(16, $clip->bitsPerSample);
        $this->assertGreaterThan(0, $clip->getByteLength());
        $this->assertStringContainsString('silence.mp3', $clip->sourcePath);
    }

    public function testDecodeBufferFromString(): void
    {
        $data = file_get_contents($this->getFixturePath('silence.mp3'));
        $clip = self::$decoder->decodeBuffer($data, 'test.mp3');

        $this->assertGreaterThan(0, $clip->sampleRate);
        $this->assertGreaterThan(0, $clip->channels);
        $this->assertSame(16, $clip->bitsPerSample);
        $this->assertSame('test.mp3', $clip->sourcePath);
    }

    public function testDecodeProducesPcmData(): void
    {
        $clip = self::$decoder->decode($this->getFixturePath('silence.mp3'));

        // ~0.5s of audio, MP3 codec may add padding so allow wide tolerance
        $expectedBytes = $clip->sampleRate * $clip->channels * 2 * 0.5;
        $this->assertGreaterThan($expectedBytes * 0.3, $clip->getByteLength());
        $this->assertLessThan($expectedBytes * 2.0, $clip->getByteLength());
    }

    public function testDecodeInvalidFileThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        self::$decoder->decode('/nonexistent/file.mp3');
    }

    public function testDecodeEmptyBufferThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        self::$decoder->decodeBuffer('', 'empty.mp3');
    }

    public function testDecodeGarbageDataThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        self::$decoder->decodeBuffer(str_repeat("\x00", 100), 'garbage.mp3');
    }
}
