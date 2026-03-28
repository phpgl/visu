<?php

namespace VISU\Tests\Build;

use PHPUnit\Framework\TestCase;
use VISU\Build\BuildConfig;
use VISU\Build\PharBuilder;

class PharBuilderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/visu_phar_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    private function createConfig(array $overrides = []): BuildConfig
    {
        $projectDir = $this->tmpDir . '/project';
        mkdir($projectDir, 0755, true);

        if (!empty($overrides)) {
            file_put_contents($projectDir . '/build.json', json_encode($overrides));
        }

        return BuildConfig::load($projectDir);
    }

    public function testGenerateStubContainsPathConstants(): void
    {
        $config = $this->createConfig();
        $builder = new PharBuilder($config);
        $stub = $builder->generateStub();

        $this->assertStringContainsString('VISU_PATH_ROOT', $stub);
        $this->assertStringContainsString('VISU_PATH_CACHE', $stub);
        $this->assertStringContainsString('VISU_PATH_STORE', $stub);
        $this->assertStringContainsString('VISU_PATH_RESOURCES', $stub);
        $this->assertStringContainsString('VISU_PATH_VENDOR', $stub);
        $this->assertStringContainsString('VISU_PATH_FRAMEWORK_RESOURCES', $stub);
    }

    public function testGenerateStubHandlesMicroSapi(): void
    {
        $config = $this->createConfig();
        $builder = new PharBuilder($config);
        $stub = $builder->generateStub();

        // Must handle empty PHP_BINARY in micro SAPI
        $this->assertStringContainsString('PHP_BINARY ?: __FILE__', $stub);
    }

    public function testGenerateStubHandlesMacOsAppBundle(): void
    {
        $config = $this->createConfig();
        $builder = new PharBuilder($config);
        $stub = $builder->generateStub();

        $this->assertStringContainsString('.app/Contents/MacOS', $stub);
        $this->assertStringContainsString('/Resources', $stub);
    }

    public function testGenerateStubExtractsFrameworkResources(): void
    {
        $config = $this->createConfig();
        $builder = new PharBuilder($config);
        $stub = $builder->generateStub();

        $this->assertStringContainsString('visu-resources', $stub);
        $this->assertStringContainsString("'fonts', 'shader'", $stub);
    }

    public function testGenerateStubExtractsGameResources(): void
    {
        $config = $this->createConfig();
        $builder = new PharBuilder($config);
        $stub = $builder->generateStub();

        $this->assertStringContainsString('Extract game resources', $stub);
        $this->assertStringContainsString('VISU_PATH_RESOURCES', $stub);
    }

    public function testGenerateStubIncludesAdditionalRequires(): void
    {
        $config = $this->createConfig([
            'phar' => [
                'additionalRequires' => [
                    'src/helpers.php',
                    'src/globals.php',
                ],
            ],
        ]);
        $builder = new PharBuilder($config);
        $stub = $builder->generateStub();

        $this->assertStringContainsString("require_once \$pharBase . '/src/helpers.php'", $stub);
        $this->assertStringContainsString("require_once \$pharBase . '/src/globals.php'", $stub);
    }

    public function testGenerateStubIncludesRunCommand(): void
    {
        $config = $this->createConfig([
            'run' => '\\App\\Game::run($container);',
        ]);
        $builder = new PharBuilder($config);
        $stub = $builder->generateStub();

        $this->assertStringContainsString('\\App\\Game::run($container);', $stub);
    }

    public function testGenerateStubOmitsRunWhenEmpty(): void
    {
        $config = $this->createConfig();
        $builder = new PharBuilder($config);
        $stub = $builder->generateStub();

        // Should end with __HALT_COMPILER without a run call
        $this->assertStringContainsString('__HALT_COMPILER();', $stub);
    }

    public function testGenerateStubEndsWithHaltCompiler(): void
    {
        $config = $this->createConfig();
        $builder = new PharBuilder($config);
        $stub = $builder->generateStub();

        $this->assertStringEndsWith('__HALT_COMPILER();', $stub);
    }

    public function testGenerateStubExtractsAppCtn(): void
    {
        $config = $this->createConfig();
        $builder = new PharBuilder($config);
        $stub = $builder->generateStub();

        $this->assertStringContainsString('app.ctn', $stub);
        $this->assertStringContainsString('container_map.php', $stub);
    }

    public function testGenerateStubIncludesEngineLog(): void
    {
        $config = $this->createConfig();
        $builder = new PharBuilder($config);
        $stub = $builder->generateStub();

        $this->assertStringContainsString('engine.log', $stub);
        $this->assertStringContainsString('set_error_handler', $stub);
        $this->assertStringContainsString('set_exception_handler', $stub);
        $this->assertStringContainsString('register_shutdown_function', $stub);
    }

    public function testStageCreatesDirectory(): void
    {
        $projectDir = $this->tmpDir . '/project';
        mkdir($projectDir . '/src', 0755, true);
        file_put_contents($projectDir . '/src/App.php', '<?php class App {}');
        file_put_contents($projectDir . '/bootstrap.php', '<?php // bootstrap');

        $config = BuildConfig::load($projectDir);
        $builder = new PharBuilder($config);

        $stagingDir = $this->tmpDir . '/staging';
        $builder->stage($stagingDir);

        $this->assertDirectoryExists($stagingDir);
        $this->assertDirectoryExists($stagingDir . '/src');
        $this->assertFileExists($stagingDir . '/src/App.php');
        $this->assertFileExists($stagingDir . '/bootstrap.php');
    }

    public function testStageExcludesExternalResources(): void
    {
        $projectDir = $this->tmpDir . '/project';
        mkdir($projectDir . '/resources/audio', 0755, true);
        mkdir($projectDir . '/resources/locales', 0755, true);
        file_put_contents($projectDir . '/resources/audio/music.ogg', 'audio-data');
        file_put_contents($projectDir . '/resources/locales/en.json', '{}');

        file_put_contents($projectDir . '/build.json', json_encode([
            'resources' => ['external' => ['resources/audio']],
        ]));

        $config = BuildConfig::load($projectDir);
        $builder = new PharBuilder($config);

        $stagingDir = $this->tmpDir . '/staging';
        $builder->stage($stagingDir);

        $this->assertDirectoryDoesNotExist($stagingDir . '/resources/audio');
        $this->assertFileExists($stagingDir . '/resources/locales/en.json');
    }
}
