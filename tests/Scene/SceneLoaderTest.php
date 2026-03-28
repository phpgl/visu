<?php

namespace VISU\Tests\Scene;

use PHPUnit\Framework\TestCase;
use VISU\Component\NameComponent;
use VISU\Component\SpriteRenderer;
use VISU\ECS\ComponentRegistry;
use VISU\ECS\EntityRegistry;
use VISU\Geo\Transform;
use VISU\Scene\SceneLoader;

class SceneLoaderTest extends TestCase
{
    private ComponentRegistry $componentRegistry;
    private SceneLoader $loader;
    private EntityRegistry $entities;

    protected function setUp(): void
    {
        $this->componentRegistry = new ComponentRegistry();
        $this->componentRegistry->register('SpriteRenderer', SpriteRenderer::class);

        $this->loader = new SceneLoader($this->componentRegistry);
        $this->entities = new EntityRegistry();
        $this->entities->registerComponent(Transform::class);
        $this->entities->registerComponent(NameComponent::class);
        $this->entities->registerComponent(SpriteRenderer::class);
    }

    public function testLoadArrayCreatesEntities(): void
    {
        $data = [
            'entities' => [
                [
                    'name' => 'Player',
                    'transform' => [
                        'position' => [10, 20, 0],
                        'scale' => [2, 2, 1],
                    ],
                    'components' => [
                        ['type' => 'SpriteRenderer', 'sprite' => 'player.png'],
                    ],
                ],
                [
                    'name' => 'Enemy',
                    'transform' => [
                        'position' => [50, 50, 0],
                    ],
                ],
            ],
        ];

        $entityIds = $this->loader->loadArray($data, $this->entities);

        $this->assertCount(2, $entityIds);

        // Check first entity
        $playerId = $entityIds[0];
        $name = $this->entities->get($playerId, NameComponent::class);
        $this->assertSame('Player', $name->name);

        $transform = $this->entities->get($playerId, Transform::class);
        $this->assertEqualsWithDelta(10.0, $transform->position->x, 0.001);
        $this->assertEqualsWithDelta(20.0, $transform->position->y, 0.001);
        $this->assertEqualsWithDelta(2.0, $transform->scale->x, 0.001);

        $sprite = $this->entities->get($playerId, SpriteRenderer::class);
        $this->assertSame('player.png', $sprite->sprite);

        // Check second entity
        $enemyId = $entityIds[1];
        $enemyName = $this->entities->get($enemyId, NameComponent::class);
        $this->assertSame('Enemy', $enemyName->name);
    }

    public function testLoadArrayWithChildren(): void
    {
        $data = [
            'entities' => [
                [
                    'name' => 'Parent',
                    'transform' => ['position' => [0, 0, 0]],
                    'children' => [
                        [
                            'name' => 'Child',
                            'transform' => ['position' => [5, 5, 0]],
                        ],
                    ],
                ],
            ],
        ];

        $entityIds = $this->loader->loadArray($data, $this->entities);

        $this->assertCount(2, $entityIds);

        $parentId = $entityIds[0];
        $childId = $entityIds[1];

        $childTransform = $this->entities->get($childId, Transform::class);
        $this->assertSame($parentId, $childTransform->parent);
    }

    public function testLoadArrayWithNamedKeys(): void
    {
        $data = [
            'entities' => [
                [
                    'name' => 'Test',
                    'transform' => [
                        'position' => ['x' => 1, 'y' => 2, 'z' => 3],
                        'scale' => ['x' => 4, 'y' => 5, 'z' => 6],
                    ],
                ],
            ],
        ];

        $entityIds = $this->loader->loadArray($data, $this->entities);
        $transform = $this->entities->get($entityIds[0], Transform::class);

        $this->assertEqualsWithDelta(1.0, $transform->position->x, 0.001);
        $this->assertEqualsWithDelta(2.0, $transform->position->y, 0.001);
        $this->assertEqualsWithDelta(3.0, $transform->position->z, 0.001);
    }
}
