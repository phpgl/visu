<?php

namespace VISU\Tests\Build;

use PHPUnit\Framework\TestCase;
use VISU\Command\BuildCommand;

class BuildCommandTest extends TestCase
{
    public function testCommandHasDescription(): void
    {
        $command = new BuildCommand();
        $this->assertNotEmpty($command->getCommandShortDescription());
    }

    public function testCommandHasExpectedArguments(): void
    {
        $command = new BuildCommand();
        $args = $command->getExpectedArguments([]);

        $this->assertArrayHasKey('platform', $args);
        $this->assertArrayHasKey('dry-run', $args);
        $this->assertArrayHasKey('micro-sfx', $args);
        $this->assertArrayHasKey('output', $args);
    }

    public function testResolveTargetsAutoDetect(): void
    {
        $command = new BuildCommand();
        $ref = new \ReflectionMethod($command, 'resolveTargets');
        $ref->setAccessible(true);

        $targets = $ref->invoke($command, '');
        $this->assertCount(1, $targets);

        $target = array_values($targets)[0];
        $this->assertArrayHasKey('platform', $target);
        $this->assertArrayHasKey('arch', $target);
    }

    public function testResolveTargetsAll(): void
    {
        $command = new BuildCommand();
        $ref = new \ReflectionMethod($command, 'resolveTargets');
        $ref->setAccessible(true);

        $targets = $ref->invoke($command, 'all');
        $this->assertCount(3, $targets);
        $this->assertArrayHasKey('macos-arm64', $targets);
        $this->assertArrayHasKey('linux-x86_64', $targets);
        $this->assertArrayHasKey('windows-x86_64', $targets);
    }

    public function testResolveTargetsExact(): void
    {
        $command = new BuildCommand();
        $ref = new \ReflectionMethod($command, 'resolveTargets');
        $ref->setAccessible(true);

        $targets = $ref->invoke($command, 'linux-x86_64');
        $this->assertCount(1, $targets);
        $this->assertArrayHasKey('linux-x86_64', $targets);
        $this->assertSame('linux', $targets['linux-x86_64']['platform']);
        $this->assertSame('x86_64', $targets['linux-x86_64']['arch']);
    }

    public function testResolveTargetsPlatformOnly(): void
    {
        $command = new BuildCommand();
        $ref = new \ReflectionMethod($command, 'resolveTargets');
        $ref->setAccessible(true);

        $targets = $ref->invoke($command, 'linux');
        $this->assertCount(1, $targets);
        $this->assertArrayHasKey('linux-x86_64', $targets);
    }

    public function testResolveTargetsUnknownThrows(): void
    {
        $command = new BuildCommand();
        $ref = new \ReflectionMethod($command, 'resolveTargets');
        $ref->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $ref->invoke($command, 'freebsd');
    }
}
