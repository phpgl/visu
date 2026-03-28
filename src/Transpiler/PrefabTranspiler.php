<?php

namespace VISU\Transpiler;

use VISU\ECS\ComponentRegistry;

class PrefabTranspiler
{
    private SceneTranspiler $sceneTranspiler;

    public function __construct(
        ComponentRegistry $componentRegistry,
    ) {
        $this->sceneTranspiler = new SceneTranspiler($componentRegistry);
    }

    /**
     * Transpiles a prefab JSON file to a PHP factory class.
     *
     * @param string $jsonPath Path to the source JSON file
     * @param string $className Short class name (e.g. "Employee")
     * @param string $namespace PHP namespace for the generated class
     * @return string Generated PHP source code
     */
    public function transpile(string $jsonPath, string $className, string $namespace = 'VISU\\Generated\\Prefabs'): string
    {
        $json = file_get_contents($jsonPath);
        if ($json === false) {
            throw new \RuntimeException("Failed to read prefab file: {$jsonPath}");
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON in prefab file: {$jsonPath}");
        }

        return $this->transpileArray($data, $className, $namespace, $jsonPath);
    }

    /**
     * Transpiles a prefab data array to a PHP factory class.
     * Prefabs are single entity definitions — we wrap them as a scene.
     *
     * @param array<string, mixed> $data Single entity definition
     * @return string Generated PHP source code
     */
    public function transpileArray(array $data, string $className, string $namespace = 'VISU\\Generated\\Prefabs', ?string $sourcePath = null): string
    {
        // Wrap single entity as scene format
        $sceneData = ['entities' => [$data]];
        return $this->sceneTranspiler->transpileArray($sceneData, $className, $namespace, $sourcePath);
    }
}
