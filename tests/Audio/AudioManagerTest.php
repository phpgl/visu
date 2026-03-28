<?php

namespace VISU\Tests\Audio;

use PHPUnit\Framework\TestCase;
use VISU\Audio\AudioBackendInterface;
use VISU\Audio\AudioChannel;
use VISU\Audio\AudioClipData;
use VISU\Audio\AudioManager;

class AudioManagerTest extends TestCase
{
    private function createMockBackend(): AudioBackendInterface
    {
        return new class implements AudioBackendInterface {
            /** @var array<string, int> */
            public array $calls = [];
            public int $streamQueuedReturn = 99999;

            public function loadWav(string $path): AudioClipData
            {
                $this->calls['loadWav'] = ($this->calls['loadWav'] ?? 0) + 1;
                return new AudioClipData(str_repeat("\x00", 512), 44100, 2, 16, $path);
            }

            public function play(AudioClipData $clip, float $volume = 1.0): void
            {
                $this->calls['play'] = ($this->calls['play'] ?? 0) + 1;
            }

            public function streamStart(AudioClipData $clip): int
            {
                $this->calls['streamStart'] = ($this->calls['streamStart'] ?? 0) + 1;
                return 1;
            }

            public function streamQueued(int $handle): int
            {
                $this->calls['streamQueued'] = ($this->calls['streamQueued'] ?? 0) + 1;
                return $this->streamQueuedReturn;
            }

            public function streamEnqueue(int $handle, AudioClipData $clip): void
            {
                $this->calls['streamEnqueue'] = ($this->calls['streamEnqueue'] ?? 0) + 1;
            }

            public function streamSetVolume(int $handle, float $volume): void
            {
                $this->calls['streamSetVolume'] = ($this->calls['streamSetVolume'] ?? 0) + 1;
            }

            public function streamStop(int $handle): void
            {
                $this->calls['streamStop'] = ($this->calls['streamStop'] ?? 0) + 1;
            }

            public function shutdown(): void
            {
                $this->calls['shutdown'] = ($this->calls['shutdown'] ?? 0) + 1;
            }

            public function getName(): string
            {
                return 'Mock';
            }

            public static function isAvailable(): bool
            {
                return true;
            }
        };
    }

    public function testBackendName(): void
    {
        $backend = $this->createMockBackend();
        $manager = new AudioManager($backend);

        $this->assertSame('Mock', $manager->getBackendName());
    }

    public function testPlaySound(): void
    {
        $backend = $this->createMockBackend();
        $manager = new AudioManager($backend);

        $manager->playSound('/sfx/boom.wav');

        $this->assertSame(1, $backend->calls['loadWav']);
        $this->assertSame(1, $backend->calls['play']);
    }

    public function testClipCaching(): void
    {
        $backend = $this->createMockBackend();
        $manager = new AudioManager($backend);

        $manager->loadClip('/sfx/boom.wav');
        $manager->loadClip('/sfx/boom.wav');
        $manager->loadClip('/sfx/boom.wav');

        // loadWav should only be called once due to caching
        $this->assertSame(1, $backend->calls['loadWav']);
    }

    public function testPlayMusic(): void
    {
        $backend = $this->createMockBackend();
        $manager = new AudioManager($backend);

        $manager->playMusic('/music/theme.wav');

        $this->assertTrue($manager->isMusicPlaying());
        $this->assertSame(1, $backend->calls['streamStart']);
    }

    public function testStopMusic(): void
    {
        $backend = $this->createMockBackend();
        $manager = new AudioManager($backend);

        $manager->playMusic('/music/theme.wav');
        $manager->stopMusic();

        $this->assertFalse($manager->isMusicPlaying());
        $this->assertSame(1, $backend->calls['streamStop']);
    }

    public function testMusicLooping(): void
    {
        $backend = $this->createMockBackend();
        $backend->streamQueuedReturn = 0; // Buffer empty -> should re-enqueue
        $manager = new AudioManager($backend);

        $manager->playMusic('/music/theme.wav');
        $manager->update();

        $this->assertSame(1, $backend->calls['streamEnqueue'] ?? 0);
    }

    public function testMusicNoRequeueWhenBuffered(): void
    {
        $backend = $this->createMockBackend();
        $backend->streamQueuedReturn = 99999; // Buffer full -> no re-enqueue
        $manager = new AudioManager($backend);

        $manager->playMusic('/music/theme.wav');
        $manager->update();

        $this->assertSame(0, $backend->calls['streamEnqueue'] ?? 0);
    }

    public function testChannelVolume(): void
    {
        $backend = $this->createMockBackend();
        $manager = new AudioManager($backend);

        $this->assertSame(1.0, $manager->getChannelVolume(AudioChannel::SFX));

        $manager->setChannelVolume(AudioChannel::SFX, 0.5);
        $this->assertSame(0.5, $manager->getChannelVolume(AudioChannel::SFX));

        // Clamping
        $manager->setChannelVolume(AudioChannel::Music, 2.0);
        $this->assertSame(1.0, $manager->getChannelVolume(AudioChannel::Music));

        $manager->setChannelVolume(AudioChannel::Music, -1.0);
        $this->assertSame(0.0, $manager->getChannelVolume(AudioChannel::Music));
    }

    public function testUnloadClip(): void
    {
        $backend = $this->createMockBackend();
        $manager = new AudioManager($backend);

        $manager->loadClip('/sfx/boom.wav');
        $manager->unloadClip('/sfx/boom.wav');
        $manager->loadClip('/sfx/boom.wav');

        // Should call loadWav twice since cache was cleared for that path
        $this->assertSame(2, $backend->calls['loadWav']);
    }

    public function testClearCache(): void
    {
        $backend = $this->createMockBackend();
        $manager = new AudioManager($backend);

        $manager->loadClip('/sfx/a.wav');
        $manager->loadClip('/sfx/b.wav');
        $manager->clearCache();
        $manager->loadClip('/sfx/a.wav');

        $this->assertSame(3, $backend->calls['loadWav']);
    }

    public function testPlayMusicStopsPrevious(): void
    {
        $backend = $this->createMockBackend();
        $manager = new AudioManager($backend);

        $manager->playMusic('/music/a.wav');
        $manager->playMusic('/music/b.wav');

        // First stream should have been stopped
        $this->assertSame(1, $backend->calls['streamStop']);
        // Two streams started
        $this->assertSame(2, $backend->calls['streamStart']);
    }
}
