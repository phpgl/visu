<?php

namespace VISU\Tests\Build;

use PHPUnit\Framework\TestCase;
use VISU\Build\BuildConfig;
use VISU\Build\PlatformPackager;

class PlatformPackagerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/visu_packager_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    private function createConfig(array $overrides = []): BuildConfig
    {
        $projectDir = $this->tmpDir . '/project';
        if (!is_dir($projectDir)) {
            mkdir($projectDir, 0755, true);
        }
        if (!empty($overrides)) {
            file_put_contents($projectDir . '/build.json', json_encode($overrides));
        }
        return BuildConfig::load($projectDir);
    }

    private function createFakeBinary(): string
    {
        $path = $this->tmpDir . '/fake-binary';
        file_put_contents($path, 'fake-executable-content');
        chmod($path, 0755);
        return $path;
    }

    public function testMacOsCreatesAppBundle(): void
    {
        $config = $this->createConfig(['name' => 'TestGame']);
        $packager = new PlatformPackager($config);
        $binary = $this->createFakeBinary();
        $outputDir = $this->tmpDir . '/output';

        $result = $packager->package($binary, $outputDir, 'macos');

        $this->assertStringEndsWith('TestGame.app', $result);
        $this->assertDirectoryExists($result . '/Contents/MacOS');
        $this->assertDirectoryExists($result . '/Contents/Resources');
        $this->assertFileExists($result . '/Contents/MacOS/TestGame');
        $this->assertFileExists($result . '/Contents/Info.plist');
    }

    public function testMacOsInfoPlistContent(): void
    {
        $config = $this->createConfig([
            'name' => 'MyGame',
            'identifier' => 'com.test.mygame',
            'version' => '2.1.0',
            'platforms' => [
                'macos' => ['minimumVersion' => '13.0'],
            ],
        ]);
        $packager = new PlatformPackager($config);
        $binary = $this->createFakeBinary();
        $outputDir = $this->tmpDir . '/output';

        $packager->package($binary, $outputDir, 'macos');

        $plist = file_get_contents($outputDir . '/MyGame.app/Contents/Info.plist');
        $this->assertStringContainsString('com.test.mygame', $plist);
        $this->assertStringContainsString('2.1.0', $plist);
        $this->assertStringContainsString('MyGame', $plist);
        $this->assertStringContainsString('13.0', $plist);
    }

    public function testLinuxCreatesFlat(): void
    {
        $config = $this->createConfig(['name' => 'LinuxGame']);
        $packager = new PlatformPackager($config);
        $binary = $this->createFakeBinary();
        $outputDir = $this->tmpDir . '/output';

        $result = $packager->package($binary, $outputDir, 'linux');

        $this->assertStringEndsWith('LinuxGame', $result);
        $this->assertFileExists($result . '/LinuxGame');
    }

    public function testWindowsCreatesExe(): void
    {
        $config = $this->createConfig(['name' => 'WinGame']);
        $packager = new PlatformPackager($config);
        $binary = $this->createFakeBinary();
        $outputDir = $this->tmpDir . '/output';

        $result = $packager->package($binary, $outputDir, 'windows');

        $this->assertFileExists($result . '/WinGame.exe');
    }

    public function testUnsupportedPlatformThrows(): void
    {
        $config = $this->createConfig();
        $packager = new PlatformPackager($config);
        $binary = $this->createFakeBinary();
        $outputDir = $this->tmpDir . '/output';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported platform');
        $packager->package($binary, $outputDir, 'freebsd');
    }

    public function testBinaryIsExecutable(): void
    {
        $config = $this->createConfig(['name' => 'ExecTest']);
        $packager = new PlatformPackager($config);
        $binary = $this->createFakeBinary();
        $outputDir = $this->tmpDir . '/output';

        $packager->package($binary, $outputDir, 'macos');

        $this->assertTrue(is_executable($outputDir . '/ExecTest.app/Contents/MacOS/ExecTest'));
    }
}
