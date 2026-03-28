<?php

namespace VISU\Tests\Build;

use PHPUnit\Framework\TestCase;
use VISU\Build\StaticPhpResolver;

class StaticPhpResolverTest extends TestCase
{
    public function testDetectPlatform(): void
    {
        $platform = StaticPhpResolver::detectPlatform();
        $this->assertContains($platform, ['macos', 'linux', 'windows']);
    }

    public function testDetectArch(): void
    {
        $arch = StaticPhpResolver::detectArch();
        $this->assertContains($arch, ['arm64', 'x86_64']);
    }

    public function testResolveWithExplicitPath(): void
    {
        $resolver = new StaticPhpResolver();
        $tmpFile = tempnam(sys_get_temp_dir(), 'micro_test_');
        file_put_contents($tmpFile, 'fake-binary');

        try {
            $result = $resolver->resolve($tmpFile, 'macos', 'arm64');
            $this->assertSame($tmpFile, $result);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testResolveWithExplicitPathNotFoundThrows(): void
    {
        $resolver = new StaticPhpResolver();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found');
        $resolver->resolve('/nonexistent/micro.sfx', 'macos', 'arm64');
    }

    public function testResolveWithoutCacheOrDownloadThrows(): void
    {
        // Use a custom HOME so cache is empty
        $oldHome = getenv('HOME');
        $tmpHome = sys_get_temp_dir() . '/visu_resolver_test_' . uniqid();
        mkdir($tmpHome, 0755, true);
        putenv("HOME={$tmpHome}");

        try {
            $resolver = new StaticPhpResolver();
            $this->expectException(\RuntimeException::class);
            $resolver->resolve(null, 'fakeos', 'fakearch');
        } finally {
            putenv("HOME={$oldHome}");
            @rmdir($tmpHome);
        }
    }

    public function testCacheAndResolve(): void
    {
        $tmpHome = sys_get_temp_dir() . '/visu_resolver_cache_test_' . uniqid();
        mkdir($tmpHome, 0755, true);
        $oldHome = getenv('HOME');
        putenv("HOME={$tmpHome}");

        try {
            $resolver = new StaticPhpResolver();

            // Create a fake binary
            $tmpFile = tempnam(sys_get_temp_dir(), 'micro_test_');
            file_put_contents($tmpFile, 'fake-micro-sfx');

            // Cache it
            $cachedPath = $resolver->cache($tmpFile, 'testplatform', 'testarch');
            $this->assertFileExists($cachedPath);
            $this->assertSame('fake-micro-sfx', file_get_contents($cachedPath));

            // Now resolve should find it
            $resolved = $resolver->resolve(null, 'testplatform', 'testarch');
            $this->assertSame($cachedPath, $resolved);
        } finally {
            putenv("HOME={$oldHome}");
            @unlink($tmpFile);
            // Clean up cache dir
            exec('rm -rf ' . escapeshellarg($tmpHome));
        }
    }

    public function testLoggerIsCalled(): void
    {
        $tmpHome = sys_get_temp_dir() . '/visu_resolver_log_test_' . uniqid();
        mkdir($tmpHome, 0755, true);
        $oldHome = getenv('HOME');
        putenv("HOME={$tmpHome}");

        $messages = [];
        try {
            $resolver = new StaticPhpResolver();
            $resolver->setLogger(function (string $msg) use (&$messages) {
                $messages[] = $msg;
            });

            try {
                $resolver->resolve(null, 'noos', 'noarch');
            } catch (\RuntimeException) {
                // Expected
            }

            $this->assertNotEmpty($messages);
            $this->assertStringContainsString('noos-noarch', $messages[0]);
        } finally {
            putenv("HOME={$oldHome}");
            @rmdir($tmpHome);
        }
    }
}
