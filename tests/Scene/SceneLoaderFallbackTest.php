<?php

namespace VISU\Tests\Scene;

use PHPUnit\Framework\TestCase;
use VISU\ECS\ComponentRegistry;
use VISU\Scene\SceneLoader;

class SceneLoaderFallbackTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/visu_loader_test_' . uniqid();
        mkdir($this->tmpDir . '/Scenes', 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        $this->removeDir($this->tmpDir);
    }

    public function testLoadsJsonWhenNoTranspiledDirSet(): void
    {
        $registry = new ComponentRegistry();
        $loader = new SceneLoader($registry);

        $jsonPath = $this->tmpDir . '/test_scene.json';
        file_put_contents($jsonPath, json_encode([
            'entities' => [
                ['name' => 'TestEntity', 'transform' => ['position' => [1, 2, 3]]],
            ],
        ]));

        // Create a mock entities interface
        $entities = $this->createMockEntities();

        $ids = $loader->loadFile($jsonPath, $entities);
        $this->assertNotEmpty($ids);
    }

    public function testFallsBackToJsonWhenTranspiledNotFound(): void
    {
        $registry = new ComponentRegistry();
        $loader = new SceneLoader($registry);
        $loader->setTranspiledDir($this->tmpDir);

        $jsonPath = $this->tmpDir . '/missing_factory.json';
        file_put_contents($jsonPath, json_encode([
            'entities' => [
                ['name' => 'FallbackEntity', 'transform' => []],
            ],
        ]));

        $entities = $this->createMockEntities();
        $ids = $loader->loadFile($jsonPath, $entities);
        $this->assertNotEmpty($ids);
    }

    public function testUsesTranspiledFactoryWhenAvailable(): void
    {
        $registry = new ComponentRegistry();
        $loader = new SceneLoader($registry);
        $loader->setTranspiledDir($this->tmpDir);

        // Create a transpiled factory PHP file
        $factoryCode = <<<'PHP'
<?php

namespace VISU\Generated\Scenes;

use VISU\ECS\EntitiesInterface;

class MyTestScene
{
    /** @return array<int> */
    public static function load(EntitiesInterface $entities): array
    {
        $ids = [];
        $ids[] = $entities->create();
        $ids[] = $entities->create();
        return $ids;
    }
}
PHP;
        file_put_contents($this->tmpDir . '/Scenes/MyTestScene.php', $factoryCode);

        // The JSON file that matches (my_test_scene.json -> MyTestScene)
        $jsonPath = $this->tmpDir . '/my_test_scene.json';
        file_put_contents($jsonPath, '{"entities":[]}');

        $entities = $this->createMockEntities();
        $ids = $loader->loadFile($jsonPath, $entities);

        // The factory creates 2 entities
        $this->assertCount(2, $ids);
    }

    public function testToClassNameMapping(): void
    {
        $registry = new ComponentRegistry();
        $loader = new SceneLoader($registry);

        $ref = new \ReflectionMethod($loader, 'toClassName');
        $ref->setAccessible(true);

        $this->assertSame('OfficeLevel1', $ref->invoke($loader, 'office_level1'));
        $this->assertSame('MainMenu', $ref->invoke($loader, 'main-menu'));
        $this->assertSame('Hud', $ref->invoke($loader, 'hud'));
    }

    private function createMockEntities(): \VISU\ECS\EntitiesInterface
    {
        $nextId = 1;
        $mock = $this->createMock(\VISU\ECS\EntitiesInterface::class);
        $mock->method('create')->willReturnCallback(function () use (&$nextId) {
            return $nextId++;
        });
        $mock->method('attach')->willReturnCallback(function (int $entity, object $component) {
            return $component;
        });

        return $mock;
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
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
