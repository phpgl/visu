<?php

namespace VISU\Tests\Audio;

use PHPUnit\Framework\TestCase;
use VISU\Audio\AudioClipData;

class AudioClipDataTest extends TestCase
{
    public function testConstructAndGetByteLength(): void
    {
        $pcm = str_repeat("\x00", 1024);
        $clip = new AudioClipData($pcm, 44100, 2, 16, '/test.wav');

        $this->assertSame(1024, $clip->getByteLength());
        $this->assertSame(44100, $clip->sampleRate);
        $this->assertSame(2, $clip->channels);
        $this->assertSame(16, $clip->bitsPerSample);
        $this->assertSame('/test.wav', $clip->sourcePath);
    }

    public function testEmptyClip(): void
    {
        $clip = new AudioClipData('', 22050, 1, 8, '/empty.wav');
        $this->assertSame(0, $clip->getByteLength());
    }
}
