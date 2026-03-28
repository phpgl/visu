<?php

namespace VISU\Scene;

use VISU\ECS\EntitiesInterface;

class PrefabManager
{
    /**
     * Cached prefab data: path => parsed JSON array.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $prefabCache = [];

    public function __construct(
        private SceneLoader $loader,
        private string $basePath = '',
    ) {
    }

    /**
     * Loads a prefab definition from a JSON file.
     * Prefab files have the same format as scene entity definitions:
     *
     * {
     *   "name": "Employee",
     *   "transform": { ... },
     *   "components": [ ... ],
     *   "children": [ ... ]
     * }
     *
     * @return array<string, mixed>
     */
    public function loadPrefab(string $path): array
    {
        if (isset($this->prefabCache[$path])) {
            return $this->prefabCache[$path];
        }

        $fullPath = $this->basePath !== ''
            ? rtrim($this->basePath, '/') . '/' . ltrim($path, '/')
            : $path;

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("Prefab file not found: {$fullPath}");
        }

        $json = file_get_contents($fullPath);
        if ($json === false) {
            throw new \RuntimeException("Failed to read prefab file: {$fullPath}");
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON in prefab file: {$fullPath}");
        }

        $this->prefabCache[$path] = $data;
        return $data;
    }

    /**
     * Instantiates a prefab, creating entities in the registry.
     * Supports property overrides merged onto the prefab definition.
     *
     * @param array<string, mixed> $overrides Override properties (name, transform, components).
     * @return array<int> Created entity IDs.
     */
    public function instantiate(string $path, EntitiesInterface $entities, array $overrides = []): array
    {
        $prefabData = $this->loadPrefab($path);
        $merged = $this->mergeOverrides($prefabData, $overrides);

        // Wrap in scene format for the loader
        $sceneData = ['entities' => [$merged]];
        return $this->loader->loadArray($sceneData, $entities);
    }

    /**
     * Merges override values onto a prefab definition.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function mergeOverrides(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if ($key === 'transform' && isset($base['transform']) && is_array($value)) {
                // Merge transform sub-keys
                $base['transform'] = array_merge($base['transform'], $value);
            } elseif ($key === 'components' && is_array($value)) {
                // Merge/override components by type
                $base['components'] = $this->mergeComponents(
                    $base['components'] ?? [],
                    $value
                );
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Merges component arrays by type. Overrides win for matching types.
     *
     * @param array<int, array<string, mixed>> $baseComponents
     * @param array<int, array<string, mixed>> $overrideComponents
     * @return array<int, array<string, mixed>>
     */
    private function mergeComponents(array $baseComponents, array $overrideComponents): array
    {
        // Index base by type
        $byType = [];
        foreach ($baseComponents as $i => $comp) {
            $type = $comp['type'] ?? null;
            if ($type !== null) {
                $byType[$type] = $i;
            }
        }

        foreach ($overrideComponents as $override) {
            $type = $override['type'] ?? null;
            if ($type === null) {
                continue;
            }

            if (isset($byType[$type])) {
                // Merge properties onto existing component
                $baseComponents[$byType[$type]] = array_merge(
                    $baseComponents[$byType[$type]],
                    $override
                );
            } else {
                // Add new component
                $baseComponents[] = $override;
            }
        }

        return $baseComponents;
    }

    /**
     * Clears the prefab cache.
     */
    public function clearCache(): void
    {
        $this->prefabCache = [];
    }
}
