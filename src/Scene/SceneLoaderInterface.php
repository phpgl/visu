<?php

namespace VISU\Scene;

use VISU\ECS\EntitiesInterface;

interface SceneLoaderInterface
{
    /**
     * Loads a scene JSON file and populates entities in the registry.
     *
     * @return array<int> List of created entity IDs.
     */
    public function loadFile(string $path, EntitiesInterface $entities): array;

    /**
     * Loads entities from a scene data array.
     *
     * @param array<string, mixed> $data
     * @return array<int> List of created entity IDs.
     */
    public function loadArray(array $data, EntitiesInterface $entities): array;
}
