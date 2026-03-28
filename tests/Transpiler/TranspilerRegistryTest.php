<?php

namespace VISU\Tests\Transpiler;

use PHPUnit\Framework\TestCase;
use VISU\Transpiler\TranspilerRegistry;

class TranspilerRegistryTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/visu_registry_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        $registryFile = $this->tmpDir . '/transpiler_registry.json';
        if (file_exists($registryFile)) {
            unlink($registryFile);
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testNewFileNeedsUpdate(): void
    {
        $registry = new TranspilerRegistry($this->tmpDir);
        $this->assertTrue($registry->needsUpdate('/some/file.json'));
    }

    public function testRecordedFileDoesNotNeedUpdate(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'src_');
        file_put_contents($tmpFile, '{"test": true}');

        try {
            $registry = new TranspilerRegistry($this->tmpDir);
            $registry->record($tmpFile, '/output/file.php');

            $this->assertFalse($registry->needsUpdate($tmpFile));
            $this->assertSame('/output/file.php', $registry->getOutputPath($tmpFile));
        } finally {
            unlink($tmpFile);
        }
    }

    public function testModifiedFileNeedsUpdate(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'src_');
        file_put_contents($tmpFile, '{"version": 1}');

        try {
            $registry = new TranspilerRegistry($this->tmpDir);
            $registry->record($tmpFile, '/output/file.php');
            $this->assertFalse($registry->needsUpdate($tmpFile));

            // Modify the file
            file_put_contents($tmpFile, '{"version": 2}');
            $this->assertTrue($registry->needsUpdate($tmpFile));
        } finally {
            unlink($tmpFile);
        }
    }

    public function testPersistence(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'src_');
        file_put_contents($tmpFile, 'content');

        try {
            $registry1 = new TranspilerRegistry($this->tmpDir);
            $registry1->record($tmpFile, '/out.php');
            $registry1->save();

            // Load fresh registry
            $registry2 = new TranspilerRegistry($this->tmpDir);
            $this->assertFalse($registry2->needsUpdate($tmpFile));
            $this->assertSame('/out.php', $registry2->getOutputPath($tmpFile));
        } finally {
            unlink($tmpFile);
        }
    }

    public function testRemove(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'src_');
        file_put_contents($tmpFile, 'data');

        try {
            $registry = new TranspilerRegistry($this->tmpDir);
            $registry->record($tmpFile, '/out.php');
            $this->assertFalse($registry->needsUpdate($tmpFile));

            $registry->remove($tmpFile);
            $this->assertTrue($registry->needsUpdate($tmpFile));
            $this->assertNull($registry->getOutputPath($tmpFile));
        } finally {
            unlink($tmpFile);
        }
    }

    public function testClear(): void
    {
        $registry = new TranspilerRegistry($this->tmpDir);
        $tmpFile = tempnam(sys_get_temp_dir(), 'src_');
        file_put_contents($tmpFile, 'x');

        try {
            $registry->record($tmpFile, '/out.php');
            $this->assertNotEmpty($registry->getEntries());

            $registry->clear();
            $this->assertEmpty($registry->getEntries());
        } finally {
            unlink($tmpFile);
        }
    }

    public function testNonexistentFileReturnsEmptyHash(): void
    {
        $registry = new TranspilerRegistry($this->tmpDir);
        // Record a nonexistent file — should store empty hash
        $registry->record('/nonexistent/file.json', '/out.php');
        // Any file should differ from empty hash
        $this->assertTrue($registry->needsUpdate('/other/file.json'));
    }
}
