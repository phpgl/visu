<?php

namespace VISU\Tests\Save;

use PHPUnit\Framework\TestCase;
use VISU\Save\SaveManager;
use VISU\Save\SaveSlot;
use VISU\Save\SaveSlotInfo;
use VISU\Signal\Dispatcher;
use VISU\Signals\Save\SaveSignal;

class SaveManagerTest extends TestCase
{
    private string $tmpDir;
    private SaveManager $manager;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/visu_save_test_' . uniqid();
        $this->manager = new SaveManager($this->tmpDir);
    }

    protected function tearDown(): void
    {
        // Clean up
        foreach (glob($this->tmpDir . '/*.json') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testSaveAndLoad(): void
    {
        $state = ['money' => 5000, 'level' => 3, 'name' => 'TestCorp'];
        $slot = $this->manager->save('slot1', $state, 120.5, 'Test save');

        $this->assertSame('slot1', $slot->name);
        $this->assertSame($state, $slot->gameState);
        $this->assertEqualsWithDelta(120.5, $slot->playTime, 0.01);
        $this->assertSame('Test save', $slot->description);

        $loaded = $this->manager->load('slot1');
        $this->assertSame($state, $loaded->gameState);
        $this->assertSame('slot1', $loaded->name);
        $this->assertEqualsWithDelta(120.5, $loaded->playTime, 0.01);
    }

    public function testSaveWithSceneData(): void
    {
        $state = ['score' => 100];
        $scene = ['entities' => [['name' => 'Player', 'transform' => []]]];

        $slot = $this->manager->save('slot_scene', $state, sceneData: $scene);

        $loaded = $this->manager->load('slot_scene');
        $this->assertSame($scene, $loaded->sceneData);
        $this->assertSame($state, $loaded->gameState);
    }

    public function testExists(): void
    {
        $this->assertFalse($this->manager->exists('nonexistent'));

        $this->manager->save('exists_test', ['data' => true]);
        $this->assertTrue($this->manager->exists('exists_test'));
    }

    public function testDelete(): void
    {
        $this->manager->save('to_delete', ['data' => true]);
        $this->assertTrue($this->manager->exists('to_delete'));

        $result = $this->manager->delete('to_delete');
        $this->assertTrue($result);
        $this->assertFalse($this->manager->exists('to_delete'));
    }

    public function testDeleteNonexistent(): void
    {
        $this->assertFalse($this->manager->delete('ghost'));
    }

    public function testListSlots(): void
    {
        $this->manager->save('alpha', ['a' => 1], 10.0, 'First');
        usleep(1000); // Ensure different timestamps
        $this->manager->save('beta', ['b' => 2], 20.0, 'Second');

        $slots = $this->manager->listSlots();
        $this->assertCount(2, $slots);

        // Newest first
        $this->assertSame('beta', $slots[0]->name);
        $this->assertSame('alpha', $slots[1]->name);

        $this->assertInstanceOf(SaveSlotInfo::class, $slots[0]);
        $this->assertSame('Second', $slots[0]->description);
        $this->assertEqualsWithDelta(20.0, $slots[0]->playTime, 0.01);
    }

    public function testListSlotsEmpty(): void
    {
        $slots = $this->manager->listSlots();
        $this->assertEmpty($slots);
    }

    public function testLoadNonexistentThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Save slot not found');
        $this->manager->load('doesnt_exist');
    }

    public function testSchemaVersion(): void
    {
        $this->manager->setSchemaVersion(2);
        $slot = $this->manager->save('versioned', ['x' => 1]);
        $this->assertSame(2, $slot->version);

        $loaded = $this->manager->load('versioned');
        $this->assertSame(2, $loaded->version);
    }

    public function testMigration(): void
    {
        // Save at version 1
        $this->manager->setSchemaVersion(1);
        $this->manager->save('migrate_test', ['old_field' => 'value']);

        // Create a new manager at version 2 with migration
        $manager2 = new SaveManager($this->tmpDir);
        $manager2->setSchemaVersion(2);
        $manager2->registerMigration(1, function (int $fromVersion, array $data): array {
            $data['gameState']['new_field'] = 'migrated_' . ($data['gameState']['old_field'] ?? '');
            return $data;
        });

        $loaded = $manager2->load('migrate_test');
        $this->assertSame(2, $loaded->version);
        $this->assertSame('migrated_value', $loaded->gameState['new_field']);
        $this->assertSame('value', $loaded->gameState['old_field']);
    }

    public function testMultiStepMigration(): void
    {
        $this->manager->setSchemaVersion(1);
        $this->manager->save('multi_migrate', ['v1' => true]);

        $manager3 = new SaveManager($this->tmpDir);
        $manager3->setSchemaVersion(3);
        $manager3->registerMigration(1, function (int $v, array $data): array {
            $data['gameState']['v2'] = true;
            return $data;
        });
        $manager3->registerMigration(2, function (int $v, array $data): array {
            $data['gameState']['v3'] = true;
            return $data;
        });

        $loaded = $manager3->load('multi_migrate');
        $this->assertSame(3, $loaded->version);
        $this->assertTrue($loaded->gameState['v1']);
        $this->assertTrue($loaded->gameState['v2']);
        $this->assertTrue($loaded->gameState['v3']);
    }

    public function testMissingMigrationThrows(): void
    {
        $this->manager->setSchemaVersion(1);
        $this->manager->save('no_migration', ['data' => true]);

        $manager2 = new SaveManager($this->tmpDir);
        $manager2->setSchemaVersion(2);
        // No migration registered

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No migration registered');
        $manager2->load('no_migration');
    }

    public function testAutosaveDisabled(): void
    {
        $this->manager->setAutosaveInterval(0);
        $result = $this->manager->updateAutosave(999.0, ['data' => true]);
        $this->assertNull($result);
    }

    public function testAutosaveTriggers(): void
    {
        $this->manager->setAutosaveInterval(5.0);

        // Not enough time
        $result = $this->manager->updateAutosave(3.0, ['money' => 100], 60.0);
        $this->assertNull($result);
        $this->assertFalse($this->manager->exists('autosave'));

        // Enough time accumulated
        $result = $this->manager->updateAutosave(3.0, ['money' => 200], 66.0);
        $this->assertNotNull($result);
        $this->assertSame('autosave', $result->name);
        $this->assertSame(['money' => 200], $result->gameState);
        $this->assertTrue($this->manager->exists('autosave'));
    }

    public function testCustomAutosaveSlot(): void
    {
        $this->manager->setAutosaveInterval(1.0);
        $this->manager->setAutosaveSlot('quick_save');

        $this->manager->updateAutosave(2.0, ['x' => 1]);
        $this->assertTrue($this->manager->exists('quick_save'));
    }

    public function testSignalDispatched(): void
    {
        $dispatcher = new Dispatcher();
        $manager = new SaveManager($this->tmpDir, $dispatcher);

        $signals = [];
        $dispatcher->register('save.completed', function (SaveSignal $s) use (&$signals) {
            $signals[] = $s;
        });
        $dispatcher->register('save.loaded', function (SaveSignal $s) use (&$signals) {
            $signals[] = $s;
        });
        $dispatcher->register('save.deleted', function (SaveSignal $s) use (&$signals) {
            $signals[] = $s;
        });

        $manager->save('signal_test', ['data' => true]);
        $this->assertCount(1, $signals);
        $this->assertSame('signal_test', $signals[0]->slotName);
        $this->assertSame(SaveSignal::SAVE, $signals[0]->action);

        $manager->load('signal_test');
        $this->assertCount(2, $signals);
        $this->assertSame(SaveSignal::LOAD, $signals[1]->action);

        $manager->delete('signal_test');
        $this->assertCount(3, $signals);
        $this->assertSame(SaveSignal::DELETE, $signals[2]->action);
    }

    public function testSlotNameSanitization(): void
    {
        // Slot names with special characters should be sanitized
        $this->manager->save('save../../../etc', ['hack' => false]);
        $this->assertTrue($this->manager->exists('save../../../etc'));

        $loaded = $this->manager->load('save../../../etc');
        $this->assertSame(['hack' => false], $loaded->gameState);
    }

    public function testOverwriteSlot(): void
    {
        $this->manager->save('overwrite', ['version' => 1]);
        $this->manager->save('overwrite', ['version' => 2]);

        $loaded = $this->manager->load('overwrite');
        $this->assertSame(['version' => 2], $loaded->gameState);
    }

    public function testSaveSlotFromArray(): void
    {
        $data = [
            'name' => 'test',
            'version' => 1,
            'timestamp' => 1234567890.0,
            'playTime' => 42.0,
            'description' => 'A test',
            'gameState' => ['key' => 'value'],
        ];

        $slot = SaveSlot::fromArray($data);
        $this->assertSame('test', $slot->name);
        $this->assertSame(42.0, $slot->playTime);
        $this->assertSame(['key' => 'value'], $slot->gameState);
        $this->assertNull($slot->sceneData);

        // Round-trip
        $restored = SaveSlot::fromArray($slot->toArray());
        $this->assertSame($slot->name, $restored->name);
        $this->assertSame($slot->gameState, $restored->gameState);
    }
}
