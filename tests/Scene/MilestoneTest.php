<?php

namespace VISU\Tests\Scene;

use PHPUnit\Framework\TestCase;
use VISU\Component\NameComponent;
use VISU\Component\SpriteAnimator;
use VISU\Component\SpriteRenderer;
use VISU\ECS\ComponentRegistry;
use VISU\ECS\EntityRegistry;
use VISU\Geo\Transform;
use VISU\Scene\PrefabManager;
use VISU\Scene\SceneLoader;
use VISU\Scene\SceneSaver;

class MilestoneTest extends TestCase
{
    private ComponentRegistry $componentRegistry;
    private SceneLoader $loader;
    private SceneSaver $saver;
    private PrefabManager $prefabManager;
    private EntityRegistry $entities;

    protected function setUp(): void
    {
        $this->componentRegistry = new ComponentRegistry();
        $this->componentRegistry->register('SpriteRenderer', SpriteRenderer::class);
        $this->componentRegistry->register('SpriteAnimator', SpriteAnimator::class);

        $this->loader = new SceneLoader($this->componentRegistry);
        $this->saver = new SceneSaver($this->componentRegistry);
        $this->prefabManager = new PrefabManager(
            $this->loader,
            __DIR__ . '/../../examples/office_demo'
        );

        $this->entities = new EntityRegistry();
        $this->entities->registerComponent(Transform::class);
        $this->entities->registerComponent(NameComponent::class);
        $this->entities->registerComponent(SpriteRenderer::class);
        $this->entities->registerComponent(SpriteAnimator::class);
    }

    public function testLoadOfficeSceneCreates50PlusEntities(): void
    {
        $scenePath = __DIR__ . '/../../examples/office_demo/scenes/office_level1.json';
        $entityIds = $this->loader->loadFile($scenePath, $this->entities);

        // Milestone: 50+ entities loaded from JSON
        $this->assertGreaterThanOrEqual(50, count($entityIds),
            'Milestone requires 50+ entities. Got: ' . count($entityIds));
    }

    public function testSceneHasEntityHierarchy(): void
    {
        $scenePath = __DIR__ . '/../../examples/office_demo/scenes/office_level1.json';
        $this->loader->loadFile($scenePath, $this->entities);

        // Find the "Office" root entity
        $officeEntity = null;
        foreach ($this->entities->view(NameComponent::class) as $id => $name) {
            if ($name->name === 'Office') {
                $officeEntity = $id;
                break;
            }
        }
        $this->assertNotNull($officeEntity, 'Root entity "Office" should exist');

        // Office should have no parent
        $officeTransform = $this->entities->get($officeEntity, Transform::class);
        $this->assertNull($officeTransform->parent);

        // Find a child entity and verify its parent chain
        $deskEntity = null;
        foreach ($this->entities->view(NameComponent::class) as $id => $name) {
            if ($name->name === 'Desk_A1') {
                $deskEntity = $id;
                break;
            }
        }
        $this->assertNotNull($deskEntity, 'Child entity "Desk_A1" should exist');

        $deskTransform = $this->entities->get($deskEntity, Transform::class);
        $this->assertNotNull($deskTransform->parent, 'Desk should have a parent');
    }

    public function testAllEntitiesHaveSpriteRenderers(): void
    {
        $scenePath = __DIR__ . '/../../examples/office_demo/scenes/office_level1.json';
        $this->loader->loadFile($scenePath, $this->entities);

        // All leaf entities (those with SpriteRenderer) should have valid sprite paths
        $spriteCount = 0;
        foreach ($this->entities->view(SpriteRenderer::class) as $id => $sprite) {
            $this->assertNotEmpty($sprite->sprite, "Entity {$id} has empty sprite path");
            $this->assertNotEmpty($sprite->sortingLayer, "Entity {$id} has empty sorting layer");
            $spriteCount++;
        }

        $this->assertGreaterThan(30, $spriteCount, 'Should have 30+ sprite renderers');
    }

    public function testPrefabInstantiation(): void
    {
        $entityIds = $this->prefabManager->instantiate('prefabs/employee.json', $this->entities);

        $this->assertNotEmpty($entityIds);
        $entityId = $entityIds[0];

        // Should have name, transform, sprite renderer, sprite animator
        $this->assertTrue($this->entities->has($entityId, NameComponent::class));
        $this->assertTrue($this->entities->has($entityId, Transform::class));
        $this->assertTrue($this->entities->has($entityId, SpriteRenderer::class));
        $this->assertTrue($this->entities->has($entityId, SpriteAnimator::class));

        $name = $this->entities->get($entityId, NameComponent::class);
        $this->assertSame('Employee', $name->name);
    }

    public function testPrefabWithOverrides(): void
    {
        $entityIds = $this->prefabManager->instantiate('prefabs/employee.json', $this->entities, [
            'name' => 'Senior Dev',
            'transform' => ['position' => [100, 200, 0]],
            'components' => [
                ['type' => 'SpriteRenderer', 'sprite' => 'sprites/senior_dev.png'],
            ],
        ]);

        $entityId = $entityIds[0];

        $name = $this->entities->get($entityId, NameComponent::class);
        $this->assertSame('Senior Dev', $name->name);

        $transform = $this->entities->get($entityId, Transform::class);
        $this->assertEqualsWithDelta(100.0, $transform->position->x, 0.001);

        $sprite = $this->entities->get($entityId, SpriteRenderer::class);
        $this->assertSame('sprites/senior_dev.png', $sprite->sprite);
    }

    public function testSceneRoundTrip(): void
    {
        // Load scene
        $scenePath = __DIR__ . '/../../examples/office_demo/scenes/office_level1.json';
        $entityIds = $this->loader->loadFile($scenePath, $this->entities);
        $originalCount = count($entityIds);

        // Save to array
        $savedData = $this->saver->toArray($this->entities);

        // Create new registry and reload
        $entities2 = new EntityRegistry();
        $entities2->registerComponent(Transform::class);
        $entities2->registerComponent(NameComponent::class);
        $entities2->registerComponent(SpriteRenderer::class);
        $entities2->registerComponent(SpriteAnimator::class);

        $entityIds2 = $this->loader->loadArray($savedData, $entities2);

        // Should have same number of entities after round-trip
        $this->assertSame($originalCount, count($entityIds2),
            'Round-trip should preserve entity count');
    }
}
