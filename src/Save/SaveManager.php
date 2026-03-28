<?php

namespace VISU\Save;

use VISU\ECS\EntitiesInterface;
use VISU\Scene\SceneLoader;
use VISU\Scene\SceneSaver;
use VISU\Signal\DispatcherInterface;
use VISU\Signals\Save\SaveSignal;

class SaveManager
{
    /**
     * Current schema version for save files.
     */
    private int $schemaVersion = 1;

    /**
     * Autosave interval in seconds (0 = disabled).
     */
    private float $autosaveInterval = 300.0;

    /**
     * Time since last autosave.
     */
    private float $timeSinceAutosave = 0.0;

    /**
     * Autosave slot name.
     */
    private string $autosaveSlot = 'autosave';

    /**
     * @var array<int, callable(int, array<string, mixed>): array<string, mixed>> Migration callbacks keyed by from-version number.
     */
    private array $migrations = [];

    public function __construct(
        private string $savePath,
        private ?DispatcherInterface $dispatcher = null,
    ) {
        if (!is_dir($this->savePath)) {
            mkdir($this->savePath, 0755, true);
        }
    }

    /**
     * Set the current schema version.
     */
    public function setSchemaVersion(int $version): void
    {
        $this->schemaVersion = $version;
    }

    /**
     * Register a migration from one version to the next.
     *
     * @param callable(int, array<string, mixed>): array<string, mixed> $callback
     */
    public function registerMigration(int $fromVersion, callable $callback): void
    {
        $this->migrations[$fromVersion] = $callback;
    }

    /**
     * Set autosave interval in seconds (0 = disabled).
     */
    public function setAutosaveInterval(float $seconds): void
    {
        $this->autosaveInterval = $seconds;
    }

    /**
     * Set the autosave slot name.
     */
    public function setAutosaveSlot(string $name): void
    {
        $this->autosaveSlot = $name;
    }

    /**
     * Save game state to a named slot.
     *
     * @param array<string, mixed> $gameState Arbitrary game data to persist
     * @param array<string, mixed>|null $sceneData Optional scene entity data (from SceneSaver::toArray)
     */
    public function save(
        string $slotName,
        array $gameState,
        float $playTime = 0.0,
        string $description = '',
        ?array $sceneData = null,
    ): SaveSlot {
        $slot = new SaveSlot(
            name: $slotName,
            version: $this->schemaVersion,
            timestamp: microtime(true),
            playTime: $playTime,
            description: $description,
            gameState: $gameState,
            sceneData: $sceneData,
        );

        $path = $this->slotPath($slotName);
        $json = json_encode($slot->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException("Failed to encode save data for slot: {$slotName}");
        }

        if (file_put_contents($path, $json) === false) {
            throw new \RuntimeException("Failed to write save file: {$path}");
        }

        $this->dispatcher?->dispatch('save.completed', new SaveSignal($slotName, SaveSignal::SAVE));

        return $slot;
    }

    /**
     * Load game state from a named slot.
     */
    public function load(string $slotName): SaveSlot
    {
        $path = $this->slotPath($slotName);
        if (!file_exists($path)) {
            throw new \RuntimeException("Save slot not found: {$slotName}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Failed to read save file: {$path}");
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid save file: {$path}");
        }

        // Run migrations if needed
        $data = $this->migrate($data);

        $slot = SaveSlot::fromArray($data);

        $this->dispatcher?->dispatch('save.loaded', new SaveSignal($slotName, SaveSignal::LOAD));

        return $slot;
    }

    /**
     * Check if a save slot exists.
     */
    public function exists(string $slotName): bool
    {
        return file_exists($this->slotPath($slotName));
    }

    /**
     * Delete a save slot.
     */
    public function delete(string $slotName): bool
    {
        $path = $this->slotPath($slotName);
        if (!file_exists($path)) {
            return false;
        }

        $result = unlink($path);

        if ($result) {
            $this->dispatcher?->dispatch('save.deleted', new SaveSignal($slotName, SaveSignal::DELETE));
        }

        return $result;
    }

    /**
     * List all available save slots with metadata.
     *
     * @return array<SaveSlotInfo>
     */
    public function listSlots(): array
    {
        $slots = [];
        $pattern = $this->savePath . '/*.json';

        foreach (glob($pattern) ?: [] as $file) {
            $slotName = pathinfo($file, PATHINFO_FILENAME);
            $json = file_get_contents($file);
            if ($json === false) {
                continue;
            }

            $data = json_decode($json, true);
            if (!is_array($data)) {
                continue;
            }

            $slots[] = new SaveSlotInfo(
                name: $slotName,
                timestamp: (float) ($data['timestamp'] ?? 0.0),
                playTime: (float) ($data['playTime'] ?? 0.0),
                description: (string) ($data['description'] ?? ''),
                version: (int) ($data['version'] ?? 1),
            );
        }

        // Sort by timestamp descending (newest first)
        usort($slots, fn(SaveSlotInfo $a, SaveSlotInfo $b) => $b->timestamp <=> $a->timestamp);

        return $slots;
    }

    /**
     * Update autosave timer. Call this every frame with deltaTime.
     * Returns the SaveSlot if autosave was triggered, null otherwise.
     *
     * @param array<string, mixed> $gameState Current game state
     * @param array<string, mixed>|null $sceneData Current scene data
     */
    public function updateAutosave(
        float $deltaTime,
        array $gameState,
        float $playTime = 0.0,
        ?array $sceneData = null,
    ): ?SaveSlot {
        if ($this->autosaveInterval <= 0.0) {
            return null;
        }

        $this->timeSinceAutosave += $deltaTime;

        if ($this->timeSinceAutosave >= $this->autosaveInterval) {
            $this->timeSinceAutosave = 0.0;
            return $this->save($this->autosaveSlot, $gameState, $playTime, 'Autosave', $sceneData);
        }

        return null;
    }

    /**
     * Run migrations on save data to bring it up to the current schema version.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function migrate(array $data): array
    {
        $version = (int) ($data['version'] ?? 1);

        while ($version < $this->schemaVersion) {
            if (!isset($this->migrations[$version])) {
                throw new \RuntimeException(
                    "No migration registered for save version {$version} -> " . ($version + 1)
                );
            }

            $data = ($this->migrations[$version])($version, $data);
            $version++;
            $data['version'] = $version;
        }

        return $data;
    }

    private function slotPath(string $slotName): string
    {
        // Sanitize slot name to prevent directory traversal
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $slotName);
        return $this->savePath . '/' . $safe . '.json';
    }
}
