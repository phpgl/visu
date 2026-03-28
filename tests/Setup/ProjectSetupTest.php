<?php

namespace VISU\Tests\Setup;

use PHPUnit\Framework\TestCase;
use VISU\Setup\ProjectSetup;

class ProjectSetupTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/visu_setup_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Recursively remove temp directory
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function createSetup(bool $interactive = false): ProjectSetup
    {
        $output = [];
        return new ProjectSetup(
            projectRoot: $this->tmpDir,
            interactive: $interactive,
            output: function (string $line) use (&$output): void {
                $output[] = $line;
            },
            confirm: function (string $question): bool {
                return true; // always confirm in tests
            },
        );
    }

    public function testCreatesRequiredDirectories(): void
    {
        $setup = $this->createSetup();
        $setup->run();

        $this->assertDirectoryExists($this->tmpDir . '/var/cache');
        $this->assertDirectoryExists($this->tmpDir . '/var/store');
        $this->assertDirectoryExists($this->tmpDir . '/resources');
        $this->assertDirectoryExists($this->tmpDir . '/resources/shader');
    }

    public function testCreatesAppCtn(): void
    {
        $setup = $this->createSetup();
        $setup->run();

        $file = $this->tmpDir . '/app.ctn';
        $this->assertFileExists($file);
        $this->assertStringContainsString('container configuration', file_get_contents($file) ?: '');
    }

    public function testCreatesGamePhp(): void
    {
        $setup = $this->createSetup();
        $setup->run();

        $file = $this->tmpDir . '/game.php';
        $this->assertFileExists($file);
        $this->assertStringContainsString('VISU_PATH_ROOT', file_get_contents($file) ?: '');
    }

    public function testCreatesClaudeMd(): void
    {
        $setup = $this->createSetup();
        $setup->run();

        $file = $this->tmpDir . '/CLAUDE.md';
        $this->assertFileExists($file);
        $this->assertStringContainsString('VISU', file_get_contents($file) ?: '');
    }

    public function testCreatesGitignore(): void
    {
        $setup = $this->createSetup();
        $setup->run();

        $file = $this->tmpDir . '/.gitignore';
        $this->assertFileExists($file);
        $content = file_get_contents($file) ?: '';
        $this->assertStringContainsString('/vendor/', $content);
        $this->assertStringContainsString('/var/', $content);
    }

    public function testDoesNotOverwriteExistingAppCtn(): void
    {
        $file = $this->tmpDir . '/app.ctn';
        file_put_contents($file, 'my custom config');

        $setup = $this->createSetup();
        $setup->run();

        $this->assertSame('my custom config', file_get_contents($file));
    }

    public function testDoesNotOverwriteExistingGamePhp(): void
    {
        $file = $this->tmpDir . '/game.php';
        file_put_contents($file, '<?php // my game');

        $setup = $this->createSetup();
        $setup->run();

        $this->assertSame('<?php // my game', file_get_contents($file));
    }

    public function testDoesNotOverwriteExistingClaudeMd(): void
    {
        $file = $this->tmpDir . '/CLAUDE.md';
        file_put_contents($file, '# My Project');

        $setup = $this->createSetup();
        $setup->run();

        $this->assertSame('# My Project', file_get_contents($file));
    }

    public function testAppendsToExistingGitignore(): void
    {
        $file = $this->tmpDir . '/.gitignore';
        file_put_contents($file, "*.log\n");

        $setup = $this->createSetup();
        $setup->run();

        $content = file_get_contents($file) ?: '';
        $this->assertStringContainsString('*.log', $content);
        $this->assertStringContainsString('/vendor/', $content);
        $this->assertStringContainsString('/var/', $content);
    }

    public function testGitignoreSkipsExistingEntries(): void
    {
        $file = $this->tmpDir . '/.gitignore';
        file_put_contents($file, "/vendor/\n/var/\n.DS_Store\n");

        $setup = $this->createSetup();
        $setup->run();

        // Should not duplicate entries
        $content = file_get_contents($file) ?: '';
        $this->assertSame(1, substr_count($content, '/vendor/'));
    }

    public function testSecondRunCreatesNothing(): void
    {
        $setup1 = $this->createSetup();
        $setup1->run();

        $setup2 = $this->createSetup();
        $result = $setup2->run();

        $this->assertFalse($result);
        $this->assertEmpty($setup2->getCreated());
    }

    public function testReturnsCreatedAndSkippedLists(): void
    {
        $setup = $this->createSetup();
        $setup->run();

        $this->assertNotEmpty($setup->getCreated());

        // Run again
        $setup2 = $this->createSetup();
        $setup2->run();

        $this->assertNotEmpty($setup2->getSkipped());
        $this->assertEmpty($setup2->getCreated());
    }

    public function testNonInteractiveCreatesEverything(): void
    {
        $setup = new ProjectSetup(
            projectRoot: $this->tmpDir,
            interactive: false,
            output: function (string $line): void {},
            confirm: function (string $question): bool {
                throw new \RuntimeException('Should not prompt in non-interactive mode');
            },
        );

        $setup->run();

        $this->assertFileExists($this->tmpDir . '/app.ctn');
        $this->assertFileExists($this->tmpDir . '/game.php');
        $this->assertFileExists($this->tmpDir . '/CLAUDE.md');
        $this->assertFileExists($this->tmpDir . '/.gitignore');
    }
}
