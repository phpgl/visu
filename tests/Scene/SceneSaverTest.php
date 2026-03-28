<?php

namespace VISU\Tests\Scene;

use PHPUnit\Framework\TestCase;
use VISU\Component\NameComponent;
use VISU\Component\SpriteRenderer;
use VISU\ECS\ComponentRegistry;
use VISU\ECS\EntityRegistry;
use VISU\Geo\Transform;
use VISU\Scene\SceneLoader;
use VISU\Scene\SceneSaver;
use GL\Math\Vec3;

class SceneSaverTest extends TestCase
{
    private ComponentRegistry $componentRegistry;
    private SceneSaver $saver;
    private SceneLoader $loader;
    private EntityRegistry $entities;

    protected function setUp(): void
    {
        $this->componentRegistry = new ComponentRegistry();
        $this->componentRegistry->register('SpriteRenderer', SpriteRenderer::class);

        $this->saver = new SceneSaver($this->componentRegistry);
        $this->loader = new SceneLoader($this->componentRegistry);
        $this->entities = new EntityRegistry();
        $this->entities->registerComponent(Transform::class);
        $this->entities->registerComponent(NameComponent::class);
        $this->entities->registerComponent(SpriteRenderer::class);
    }

    public function testToArraySerializesEntities(): void
    {
        $entity = $this->entities->create();
        $this->entities->attach($entity, new NameComponent('TestEntity'));

        $transform = new Transform();
        $transform->position = new Vec3(10, 20, 0);
        $this->entities->attach($entity, $transform);

        $sprite = new SpriteRenderer();
        $sprite->sprite = 'test.png';
        $this->entities->attach($entity, $sprite);

        $data = $this->saver->toArray($this->entities);

        $this->assertArrayHasKey('entities', $data);
        $this->assertCount(1, $data['entities']);

        $entityData = $data['entities'][0];
        $this->assertSame('TestEntity', $entityData['name']);
        $this->assertEqualsWithDelta(10.0, $entityData['transform']['position'][0], 0.01);
        $this->assertEqualsWithDelta(20.0, $entityData['transform']['position'][1], 0.01);

        $this->assertCount(1, $entityData['components']);
        $this->assertSame('SpriteRenderer', $entityData['components'][0]['type']);
        $this->assertSame('test.png', $entityData['components'][0]['sprite']);
    }

    public function testRoundTripPreservesData(): void
    {
        $inputData = [
            'entities' => [
                [
                    'name' => 'Player',
                    'transform' => [
                        'position' => [10, 20, 0],
                        'rotation' => [0, 0, 0],
                        'scale' => [1, 1, 1],
                    ],
                    'components' => [
                        ['type' => 'SpriteRenderer', 'sprite' => 'player.png', 'sortingLayer' => 'Default'],
                    ],
                ],
            ],
        ];

        // Load
        $this->loader->loadArray($inputData, $this->entities);

        // Save
        $outputData = $this->saver->toArray($this->entities);

        // Verify round-trip
        $this->assertSame('Player', $outputData['entities'][0]['name']);
        $this->assertEqualsWithDelta(10.0, $outputData['entities'][0]['transform']['position'][0], 0.01);
        $this->assertSame('player.png', $outputData['entities'][0]['components'][0]['sprite']);
    }

    public function testSerializesChildEntities(): void
    {
        $parent = $this->entities->create();
        $this->entities->attach($parent, new NameComponent('Parent'));
        $this->entities->attach($parent, new Transform());

        $child = $this->entities->create();
        $this->entities->attach($child, new NameComponent('Child'));
        $childTransform = new Transform();
        $childTransform->setParent($this->entities, $parent);
        $this->entities->attach($child, $childTransform);

        $data = $this->saver->toArray($this->entities);

        // Should have only 1 root entity
        $this->assertCount(1, $data['entities']);
        $this->assertSame('Parent', $data['entities'][0]['name']);

        // Child should be nested
        $this->assertCount(1, $data['entities'][0]['children']);
        $this->assertSame('Child', $data['entities'][0]['children'][0]['name']);
    }
}
