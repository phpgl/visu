<?php

namespace VISU\Tests\Build;

use PHPUnit\Framework\TestCase;
use VISU\Build\BuildConfig;

class BuildConfigTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/visu_build_config_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testDefaultValues(): void
    {
        $config = BuildConfig::load($this->tmpDir);

        $this->assertSame('Game', $config->name);
        $this->assertSame('com.visu.game', $config->identifier);
        $this->assertSame('1.0.0', $config->version);
        $this->assertSame('game.php', $config->entry);
        $this->assertSame('', $config->run);
        $this->assertContains('glfw', $config->phpExtensions);
        $this->assertContains('mbstring', $config->phpExtensions);
        $this->assertNotEmpty($config->pharExclude);
        $this->assertEmpty($config->additionalRequires);
        $this->assertEmpty($config->externalResources);
    }

    public function testLoadsFromComposerJson(): void
    {
        file_put_contents($this->tmpDir . '/composer.json', json_encode([
            'name' => 'acme/my-game',
            'version' => '2.5.0',
        ]));

        $config = BuildConfig::load($this->tmpDir);

        $this->assertSame('My-game', $config->name);
        $this->assertSame('2.5.0', $config->version);
    }

    public function testBuildJsonOverridesDefaults(): void
    {
        file_put_contents($this->tmpDir . '/build.json', json_encode([
            'name' => 'SuperGame',
            'identifier' => 'com.example.supergame',
            'version' => '3.0.0',
            'entry' => 'start.php',
            'run' => '\\App\\Game::run($container);',
            'php' => [
                'extensions' => ['glfw', 'mbstring', 'zip', 'gd'],
                'extraLibs' => ['-lc++', '-lm'],
            ],
            'phar' => [
                'exclude' => ['**/tests'],
                'additionalRequires' => ['src/helpers.php'],
            ],
            'resources' => [
                'external' => ['resources/audio', 'resources/video'],
            ],
            'platforms' => [
                'macos' => ['minimumVersion' => '13.0'],
            ],
        ]));

        $config = BuildConfig::load($this->tmpDir);

        $this->assertSame('SuperGame', $config->name);
        $this->assertSame('com.example.supergame', $config->identifier);
        $this->assertSame('3.0.0', $config->version);
        $this->assertSame('start.php', $config->entry);
        $this->assertSame('\\App\\Game::run($container);', $config->run);
        $this->assertSame(['glfw', 'mbstring', 'zip', 'gd'], $config->phpExtensions);
        $this->assertSame(['-lc++', '-lm'], $config->phpExtraLibs);
        $this->assertSame(['**/tests'], $config->pharExclude);
        $this->assertSame(['src/helpers.php'], $config->additionalRequires);
        $this->assertSame(['resources/audio', 'resources/video'], $config->externalResources);
        $this->assertArrayHasKey('macos', $config->platforms);
    }

    public function testBuildJsonOverridesComposerJson(): void
    {
        file_put_contents($this->tmpDir . '/composer.json', json_encode([
            'name' => 'acme/old-name',
            'version' => '1.0.0',
        ]));
        file_put_contents($this->tmpDir . '/build.json', json_encode([
            'name' => 'NewName',
            'version' => '2.0.0',
        ]));

        $config = BuildConfig::load($this->tmpDir);

        $this->assertSame('NewName', $config->name);
        $this->assertSame('2.0.0', $config->version);
    }

    public function testToArray(): void
    {
        $config = BuildConfig::load($this->tmpDir);
        $arr = $config->toArray();

        $this->assertArrayHasKey('name', $arr);
        $this->assertArrayHasKey('identifier', $arr);
        $this->assertArrayHasKey('version', $arr);
        $this->assertArrayHasKey('entry', $arr);
        $this->assertArrayHasKey('php.extensions', $arr);
        $this->assertArrayHasKey('phar.exclude', $arr);
        $this->assertArrayHasKey('resources.external', $arr);
    }

    public function testPartialBuildJson(): void
    {
        file_put_contents($this->tmpDir . '/build.json', json_encode([
            'name' => 'PartialGame',
        ]));

        $config = BuildConfig::load($this->tmpDir);

        $this->assertSame('PartialGame', $config->name);
        // Defaults preserved
        $this->assertSame('game.php', $config->entry);
        $this->assertContains('glfw', $config->phpExtensions);
    }

    public function testProjectRootIsSet(): void
    {
        $config = BuildConfig::load($this->tmpDir);
        $this->assertSame($this->tmpDir, $config->projectRoot);
    }
}
