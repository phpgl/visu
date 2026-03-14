<?php

namespace VISU\Tests\WorldEditor;

use PHPUnit\Framework\TestCase;
use VISU\WorldEditor\Api\WorldsController;
use VISU\WorldEditor\WorldFile;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WorldsControllerTest extends TestCase
{
    private string $worldsDir;
    private string $resourcesDir;

    protected function setUp(): void
    {
        $this->worldsDir = sys_get_temp_dir() . '/visu_ctrl_test_worlds_' . uniqid();
        $this->resourcesDir = sys_get_temp_dir() . '/visu_ctrl_test_res_' . uniqid();
        mkdir($this->worldsDir, 0755, true);
        mkdir($this->resourcesDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->worldsDir);
        $this->removeDir($this->resourcesDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function capture(WorldsController $controller, string $method, string $path): string
    {
        ob_start();
        $controller->handle($method, $path);
        return (string) ob_get_clean();
    }

    /**
     * @return array<mixed>
     */
    private function captureJson(WorldsController $controller, string $method, string $path): array
    {
        $output = $this->capture($controller, $method, $path);
        $data = json_decode($output, true);
        $this->assertIsArray($data);
        return $data;
    }

    private function makeController(): WorldsController
    {
        return new WorldsController($this->worldsDir, $this->resourcesDir);
    }

    public function testCanBeConstructed(): void
    {
        $this->assertInstanceOf(WorldsController::class, $this->makeController());
    }

    public function testListWorldsReturnsEmptyArray(): void
    {
        $data = $this->captureJson($this->makeController(), 'GET', '/api/worlds');
        $this->assertCount(0, $data);
    }

    public function testListWorldsReturnsCreatedWorlds(): void
    {
        WorldFile::create('TestMap')->save($this->worldsDir . '/testmap.world.json');

        $data = $this->captureJson($this->makeController(), 'GET', '/api/worlds');
        $this->assertCount(1, $data);
        $this->assertArrayHasKey(0, $data);
        $this->assertSame('testmap', $data[0]['name']);
    }

    public function testGetWorldReturnsWorldData(): void
    {
        WorldFile::create('GetTest')->save($this->worldsDir . '/gettest.world.json');

        $data = $this->captureJson($this->makeController(), 'GET', '/api/worlds/gettest');
        $this->assertSame('GetTest', $data['meta']['name']);
    }

    public function testGetNonExistentWorldReturnsError(): void
    {
        $data = $this->captureJson($this->makeController(), 'GET', '/api/worlds/nonexistent');
        $this->assertArrayHasKey('error', $data);
    }

    public function testGetConfigReturnsConfig(): void
    {
        $data = $this->captureJson($this->makeController(), 'GET', '/api/config');
        $this->assertArrayHasKey('tileSize', $data);
    }

    public function testBrowseAssetsReturnsEntries(): void
    {
        file_put_contents($this->resourcesDir . '/test.png', 'fake');
        mkdir($this->resourcesDir . '/subdir');

        $data = $this->captureJson($this->makeController(), 'GET', '/api/assets/browse');
        $this->assertArrayHasKey('entries', $data);
        $this->assertCount(2, $data['entries']);
        $this->assertSame('directory', $data['entries'][0]['type']);
    }

    public function testBrowseAssetsDetectsFileTypes(): void
    {
        file_put_contents($this->resourcesDir . '/sprite.png', 'x');
        file_put_contents($this->resourcesDir . '/model.glb', 'x');
        file_put_contents($this->resourcesDir . '/data.json', 'x');
        file_put_contents($this->resourcesDir . '/music.ogg', 'x');

        $data = $this->captureJson($this->makeController(), 'GET', '/api/assets/browse');
        $types = array_column($data['entries'], 'type', 'name');

        $this->assertSame('json', $types['data.json']);
        $this->assertSame('model', $types['model.glb']);
        $this->assertSame('audio', $types['music.ogg']);
        $this->assertSame('image', $types['sprite.png']);
    }

    public function testListScenesReturnsEmpty(): void
    {
        $data = $this->captureJson($this->makeController(), 'GET', '/api/scenes');
        $this->assertCount(0, $data);
    }

    public function testListScenesReturnsCreatedScenes(): void
    {
        mkdir($this->resourcesDir . '/scenes');
        file_put_contents($this->resourcesDir . '/scenes/level1.json', '{"entities":[]}');

        $data = $this->captureJson($this->makeController(), 'GET', '/api/scenes');
        $this->assertCount(1, $data);
        $this->assertArrayHasKey(0, $data);
        $this->assertSame('level1', $data[0]['name']);
    }

    public function testGetSceneReturnsContent(): void
    {
        mkdir($this->resourcesDir . '/scenes');
        file_put_contents($this->resourcesDir . '/scenes/test.json', json_encode(['entities' => [['name' => 'Player']]]));

        $data = $this->captureJson($this->makeController(), 'GET', '/api/scenes/test');
        $this->assertSame('Player', $data['entities'][0]['name']);
    }

    public function testUnknownEndpointReturnsError(): void
    {
        $data = $this->captureJson($this->makeController(), 'GET', '/api/unknown');
        $this->assertArrayHasKey('error', $data);
    }

    public function testOptionsReturnsEmpty(): void
    {
        $output = $this->capture($this->makeController(), 'OPTIONS', '/api/worlds');
        $this->assertEmpty($output);
    }

    public function testDeleteWorldRemovesFile(): void
    {
        $path = $this->worldsDir . '/todelete.world.json';
        WorldFile::create('ToDelete')->save($path);
        $this->assertFileExists($path);

        $data = $this->captureJson($this->makeController(), 'DELETE', '/api/worlds/todelete');
        $this->assertTrue($data['ok']);
        $this->assertFileDoesNotExist($path);
    }
}
