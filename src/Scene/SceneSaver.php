<?php

namespace VISU\Scene;

use VISU\Component\NameComponent;
use VISU\ECS\ComponentRegistry;
use VISU\ECS\EntitiesInterface;
use VISU\Geo\Transform;

class SceneSaver
{
    public function __construct(
        private ComponentRegistry $componentRegistry,
    ) {
    }

    /**
     * Saves all entities in the registry to a JSON file.
     *
     * @param array<int>|null $entityIds Specific entities to save, or null for all with Transform.
     */
    public function saveFile(string $path, EntitiesInterface $entities, ?array $entityIds = null): void
    {
        $data = $this->toArray($entities, $entityIds);

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException("Failed to encode scene to JSON");
        }

        if (file_put_contents($path, $json) === false) {
            throw new \RuntimeException("Failed to write scene file: {$path}");
        }
    }

    /**
     * Converts entities to a scene data array.
     *
     * @param array<int>|null $entityIds
     * @return array<string, mixed>
     */
    public function toArray(EntitiesInterface $entities, ?array $entityIds = null): array
    {
        // Collect root entities (no parent transform)
        if ($entityIds === null) {
            $entityIds = $entities->list(Transform::class);
        }

        // Filter to root entities only (parent === null)
        $rootEntities = [];
        foreach ($entityIds as $id) {
            $transform = $entities->tryGet($id, Transform::class);
            if ($transform !== null && $transform->parent === null) {
                $rootEntities[] = $id;
            }
        }

        $entityDefs = [];
        foreach ($rootEntities as $id) {
            $entityDefs[] = $this->serializeEntity($id, $entities);
        }

        return ['entities' => $entityDefs];
    }

    /**
     * Serializes a single entity and its children.
     *
     * @return array<string, mixed>
     */
    private function serializeEntity(int $entityId, EntitiesInterface $entities): array
    {
        $def = [];

        // Name
        $name = $entities->tryGet($entityId, NameComponent::class);
        if ($name !== null) {
            $def['name'] = $name->name;
        }

        // Transform
        $transform = $entities->tryGet($entityId, Transform::class);
        if ($transform !== null) {
            $def['transform'] = $this->serializeTransform($transform);
        }

        // Components (excluding Transform and NameComponent)
        $components = $entities->components($entityId);
        $componentDefs = [];
        foreach ($components as $className => $component) {
            if ($className === Transform::class || $className === NameComponent::class) {
                continue;
            }

            $typeName = $this->componentRegistry->getTypeName($className);
            if ($typeName === null) {
                continue; // Skip unregistered components
            }

            $componentDef = $this->serializeComponent($typeName, $component);
            $componentDefs[] = $componentDef;
        }

        if (!empty($componentDefs)) {
            $def['components'] = $componentDefs;
        }

        // Children — find all entities whose parent is this entity
        $children = [];
        foreach ($entities->view(Transform::class) as $childId => $childTransform) {
            if ($childTransform->parent === $entityId) {
                $children[] = $this->serializeEntity($childId, $entities);
            }
        }

        if (!empty($children)) {
            $def['children'] = $children;
        }

        return $def;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTransform(Transform $transform): array
    {
        $euler = $transform->getLocalEulerAngles();

        $rad2deg = 180.0 / M_PI;

        return [
            'position' => [
                round($transform->position->x, 4),
                round($transform->position->y, 4),
                round($transform->position->z, 4),
            ],
            'rotation' => [
                round($euler->x * $rad2deg, 4),
                round($euler->y * $rad2deg, 4),
                round($euler->z * $rad2deg, 4),
            ],
            'scale' => [
                round($transform->scale->x, 4),
                round($transform->scale->y, 4),
                round($transform->scale->z, 4),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeComponent(string $typeName, object $component): array
    {
        $def = ['type' => $typeName];

        $ref = new \ReflectionObject($component);
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $value = $prop->getValue($component);
            $def[$prop->getName()] = $value;
        }

        return $def;
    }
}
