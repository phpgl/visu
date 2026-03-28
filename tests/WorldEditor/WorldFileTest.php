<?php

namespace VISU\Tests\WorldEditor;

use PHPUnit\Framework\TestCase;
use VISU\WorldEditor\WorldFile;

class WorldFileTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/visu_worldfile_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        $files = glob($this->tmpDir . '/*') ?: [];
        foreach ($files as $file) {
            unlink($file);
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testCreateReturnsDefaultWorld(): void
    {
        $world = WorldFile::create('TestWorld');

        $this->assertSame('1.0', $world->version);
        $this->assertSame('TestWorld', $world->meta['name']);
        $this->assertSame('2d_topdown', $world->meta['type']);
        $this->assertSame(32, $world->meta['tileSize']);
        $this->assertCount(2, $world->layers);
        $this->assertSame('bg', $world->layers[0]['id']);
        $this->assertSame('tile', $world->layers[0]['type']);
        $this->assertSame('entities', $world->layers[1]['id']);
        $this->assertSame('entity', $world->layers[1]['type']);
    }

    public function testFromArrayParsesData(): void
    {
        $data = [
            'version' => '2.0',
            'meta' => ['name' => 'Custom', 'type' => '3d', 'tileSize' => 64],
            'camera' => ['position' => ['x' => 10, 'y' => 20], 'zoom' => 2.0],
            'layers' => [
                ['id' => 'l1', 'name' => 'Layer 1', 'type' => 'entity', 'entities' => []],
            ],
            'lights' => [['type' => 'point', 'position' => ['x' => 0, 'y' => 0]]],
            'tilesets' => [['id' => 'ts1', 'path' => 'tiles.png']],
        ];

        $world = WorldFile::fromArray($data);

        $this->assertSame('2.0', $world->version);
        $this->assertSame('Custom', $world->meta['name']);
        $this->assertCount(1, $world->layers);
        $this->assertCount(1, $world->lights);
        $this->assertCount(1, $world->tilesets);
    }

    public function testFromArrayWithDefaults(): void
    {
        $world = WorldFile::fromArray([]);

        $this->assertSame('1.0', $world->version);
        $this->assertSame('Untitled', $world->meta['name']);
        $this->assertEmpty($world->layers);
    }

    public function testToArrayRoundTrip(): void
    {
        $world = WorldFile::create('RoundTrip');
        $array = $world->toArray();

        $this->assertSame('1.0', $array['version']);
        $this->assertSame('RoundTrip', $array['meta']['name']);
        $this->assertCount(2, $array['layers']);
        $this->assertArrayHasKey('camera', $array);
        $this->assertArrayHasKey('lights', $array);
        $this->assertArrayHasKey('tilesets', $array);
    }

    public function testSaveAndLoad(): void
    {
        $path = $this->tmpDir . '/test.world.json';
        $world = WorldFile::create('SaveTest');
        $world->layers[1]['entities'][] = [
            'id' => 1,
            'name' => 'Player',
            'type' => 'player_spawn',
            'position' => ['x' => 100, 'y' => 200],
        ];
        $world->save($path);

        $this->assertFileExists($path);

        $loaded = WorldFile::load($path);
        $this->assertSame('SaveTest', $loaded->meta['name']);
        $this->assertCount(2, $loaded->layers);
        $this->assertCount(1, $loaded->layers[1]['entities']);
        $this->assertSame('Player', $loaded->layers[1]['entities'][0]['name']);
    }

    public function testSaveCreatesDirectory(): void
    {
        $path = $this->tmpDir . '/sub/dir/test.world.json';
        $world = WorldFile::create('DirTest');
        $world->save($path);

        $this->assertFileExists($path);

        // Clean up nested dirs
        unlink($path);
        rmdir($this->tmpDir . '/sub/dir');
        rmdir($this->tmpDir . '/sub');
    }

    public function testSaveUpdatesModifiedTimestamp(): void
    {
        $path = $this->tmpDir . '/time.world.json';
        $world = WorldFile::create('TimeTest');
        // Override to a known past timestamp
        $world->meta['modified'] = '2020-01-01T00:00:00+00:00';

        $world->save($path);

        $loaded = WorldFile::load($path);
        $this->assertNotSame('2020-01-01T00:00:00+00:00', $loaded->meta['modified']);
    }

    public function testLoadNonExistentThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('World file not found');
        WorldFile::load('/nonexistent/path.world.json');
    }

    public function testLoadInvalidJsonThrows(): void
    {
        $path = $this->tmpDir . '/invalid.world.json';
        file_put_contents($path, 'not json at all');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON');
        WorldFile::load($path);
    }

    public function testGetLayers(): void
    {
        $world = WorldFile::create('LayerTest');
        $layers = $world->getLayers();

        $this->assertCount(2, $layers);
        $this->assertSame('bg', $layers[0]['id']);
        $this->assertSame('entities', $layers[1]['id']);
    }

    public function testJsonOutputIsPrettyPrinted(): void
    {
        $path = $this->tmpDir . '/pretty.world.json';
        $world = WorldFile::create('PrettyTest');
        $world->save($path);

        $content = file_get_contents($path);
        $this->assertIsString($content);
        $this->assertStringContainsString("\n", $content);
        $this->assertStringContainsString('    ', $content);
    }
}
