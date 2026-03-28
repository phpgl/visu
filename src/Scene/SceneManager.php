<?php

namespace VISU\Scene;

use VISU\ECS\EntityRegistry;
use VISU\Signal\Dispatcher;
use VISU\Signals\Scene\SceneLoadedSignal;
use VISU\Signals\Scene\SceneUnloadedSignal;

class SceneManager
{
    /**
     * Currently active scene name.
     */
    private ?string $activeScene = null;

    /**
     * Scene stack for overlays (e.g. pause menu over gameplay).
     *
     * @var array<string>
     */
    private array $sceneStack = [];

    /**
     * Registered scene definitions: name => file path.
     *
     * @var array<string, string>
     */
    private array $scenePaths = [];

    /**
     * Entity IDs created by each scene, for cleanup on unload.
     *
     * @var array<string, array<int>>
     */
    private array $sceneEntities = [];

    /**
     * Entity IDs marked as persistent (survive scene changes).
     *
     * @var array<int, true>
     */
    private array $persistentEntities = [];

    public function __construct(
        private EntityRegistry $entities,
        private SceneLoader $loader,
        private Dispatcher $dispatcher,
    ) {
    }

    /**
     * Registers a scene by name with its JSON file path.
     */
    public function registerScene(string $name, string $path): void
    {
        $this->scenePaths[$name] = $path;
    }

    /**
     * Loads and activates a scene, unloading the previous active scene.
     */
    public function loadScene(string $name): void
    {
        // Unload current active scene
        if ($this->activeScene !== null) {
            $this->unloadScene($this->activeScene);
        }

        $this->doLoadScene($name);
        $this->activeScene = $name;
    }

    /**
     * Pushes a scene onto the stack (e.g. pause overlay).
     * The previous scene stays loaded but becomes inactive.
     */
    public function pushScene(string $name): void
    {
        if ($this->activeScene !== null) {
            $this->sceneStack[] = $this->activeScene;
        }

        $this->doLoadScene($name);
        $this->activeScene = $name;
    }

    /**
     * Pops the current scene and returns to the previous one on the stack.
     */
    public function popScene(): void
    {
        if ($this->activeScene !== null) {
            $this->unloadScene($this->activeScene);
        }

        $this->activeScene = array_pop($this->sceneStack);
    }

    /**
     * Unloads a specific scene, destroying all non-persistent entities created by it.
     */
    public function unloadScene(string $name): void
    {
        $entityIds = $this->sceneEntities[$name] ?? [];

        foreach ($entityIds as $entityId) {
            // Skip persistent entities
            if (isset($this->persistentEntities[$entityId])) {
                continue;
            }

            if ($this->entities->valid($entityId)) {
                $this->entities->destroy($entityId);
            }
        }

        unset($this->sceneEntities[$name]);

        $this->dispatcher->dispatch(
            SceneUnloadedSignal::class,
            new SceneUnloadedSignal($name)
        );
    }

    /**
     * Marks an entity as persistent (won't be destroyed on scene change).
     */
    public function markPersistent(int $entityId): void
    {
        $this->persistentEntities[$entityId] = true;
    }

    /**
     * Removes persistent mark from an entity.
     */
    public function unmarkPersistent(int $entityId): void
    {
        unset($this->persistentEntities[$entityId]);
    }

    /**
     * Returns the currently active scene name.
     */
    public function getActiveScene(): ?string
    {
        return $this->activeScene;
    }

    /**
     * Returns entity IDs created by a scene.
     *
     * @return array<int>
     */
    public function getSceneEntities(string $name): array
    {
        return $this->sceneEntities[$name] ?? [];
    }

    /**
     * Internal: loads a scene from its registered path.
     */
    private function doLoadScene(string $name): void
    {
        if (!isset($this->scenePaths[$name])) {
            throw new \RuntimeException("Scene not registered: '{$name}'");
        }

        $path = $this->scenePaths[$name];
        $entityIds = $this->loader->loadFile($path, $this->entities);
        $this->sceneEntities[$name] = $entityIds;

        $this->dispatcher->dispatch(
            SceneLoadedSignal::class,
            new SceneLoadedSignal($name, $path)
        );
    }
}
