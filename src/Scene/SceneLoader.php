<?php

namespace VISU\Scene;

use GL\Math\GLM;
use GL\Math\Quat;
use GL\Math\Vec3;
use VISU\Component\NameComponent;
use VISU\ECS\ComponentRegistry;
use VISU\ECS\EntitiesInterface;
use VISU\Geo\Transform;

class SceneLoader implements SceneLoaderInterface
{
    /**
     * Base directory for transpiled PHP factory classes.
     * When set, loadFile() will check for a transpiled version before parsing JSON.
     */
    private ?string $transpiledDir = null;

    public function __construct(
        private ComponentRegistry $componentRegistry,
    ) {
    }

    /**
     * Sets the directory where transpiled PHP factories are stored.
     * When set, loadFile() will prefer the transpiled version if available.
     */
    public function setTranspiledDir(string $dir): void
    {
        $this->transpiledDir = rtrim($dir, '/');
    }

    /**
     * Loads a scene JSON file and populates entities in the registry.
     * If a transpiled PHP factory exists (and transpiledDir is set),
     * the factory is used instead of parsing JSON at runtime.
     *
     * @return array<int> List of created entity IDs.
     */
    public function loadFile(string $path, EntitiesInterface $entities): array
    {
        // Try transpiled factory first
        if ($this->transpiledDir !== null) {
            $factoryClass = $this->resolveTranspiledClass($path, 'VISU\\Generated\\Scenes');
            if ($factoryClass !== null) {
                /** @var array<int> */
                return $factoryClass::load($entities);
            }
        }

        if (!file_exists($path)) {
            throw new \RuntimeException("Scene file not found: {$path}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Failed to read scene file: {$path}");
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON in scene file: {$path}");
        }

        return $this->loadArray($data, $entities);
    }

    /**
     * Loads entities from a scene data array.
     *
     * @param array<string, mixed> $data
     * @return array<int> List of created entity IDs.
     */
    public function loadArray(array $data, EntitiesInterface $entities): array
    {
        $createdEntities = [];
        $entityDefs = $data['entities'] ?? [];

        foreach ($entityDefs as $entityDef) {
            $created = $this->loadEntity($entityDef, $entities, null);
            array_push($createdEntities, ...$created);
        }

        return $createdEntities;
    }

    /**
     * Loads a single entity definition recursively (including children).
     *
     * @param array<string, mixed> $def
     * @return array<int> All created entity IDs (parent + children).
     */
    private function loadEntity(array $def, EntitiesInterface $entities, ?int $parentEntity): array
    {
        $entity = $entities->create();
        $created = [$entity];

        // Name component
        if (isset($def['name'])) {
            $entities->attach($entity, new NameComponent((string)$def['name']));
        }

        // Transform
        $transform = $this->buildTransform($def['transform'] ?? []);
        if ($parentEntity !== null) {
            $transform->setParent($entities, $parentEntity);
        }
        $entities->attach($entity, $transform);

        // Components
        foreach ($def['components'] ?? [] as $componentDef) {
            $typeName = $componentDef['type'] ?? null;
            if ($typeName === null) {
                continue;
            }

            // Extract properties (everything except 'type')
            $properties = $componentDef;
            unset($properties['type']);

            $component = $this->componentRegistry->create($typeName, $properties);
            $entities->attach($entity, $component);
        }

        // Children (recursive)
        foreach ($def['children'] ?? [] as $childDef) {
            $childCreated = $this->loadEntity($childDef, $entities, $entity);
            array_push($created, ...$childCreated);
        }

        return $created;
    }

    /**
     * Builds a Transform from a JSON definition.
     *
     * @param array<string, mixed> $def
     */
    private function buildTransform(array $def): Transform
    {
        $transform = new Transform();

        if (isset($def['position'])) {
            $p = $def['position'];
            if (is_array($p)) {
                $transform->position = new Vec3(
                    (float)($p[0] ?? $p['x'] ?? 0),
                    (float)($p[1] ?? $p['y'] ?? 0),
                    (float)($p[2] ?? $p['z'] ?? 0),
                );
            }
        }

        if (isset($def['rotation'])) {
            $r = $def['rotation'];
            if (is_array($r)) {
                // Euler angles in degrees -> quaternion
                $q = new Quat();
                $rx = GLM::radians((float)($r[0] ?? $r['x'] ?? 0));
                $ry = GLM::radians((float)($r[1] ?? $r['y'] ?? 0));
                $rz = GLM::radians((float)($r[2] ?? $r['z'] ?? 0));
                if ($rx != 0.0) {
                    $q->rotate($rx, new Vec3(1, 0, 0));
                }
                if ($ry != 0.0) {
                    $q->rotate($ry, new Vec3(0, 1, 0));
                }
                if ($rz != 0.0) {
                    $q->rotate($rz, new Vec3(0, 0, 1));
                }
                $transform->orientation = $q;
            }
        }

        if (isset($def['scale'])) {
            $s = $def['scale'];
            if (is_array($s)) {
                $transform->scale = new Vec3(
                    (float)($s[0] ?? $s['x'] ?? 1),
                    (float)($s[1] ?? $s['y'] ?? 1),
                    (float)($s[2] ?? $s['z'] ?? 1),
                );
            }
        }

        $transform->markDirty();
        return $transform;
    }

    /**
     * Resolves a JSON file path to its transpiled PHP factory class.
     * Returns the FQCN if the file exists and the class is loadable, null otherwise.
     *
     * @return class-string|null
     */
    private function resolveTranspiledClass(string $jsonPath, string $namespace): ?string
    {
        $baseName = pathinfo($jsonPath, PATHINFO_FILENAME);
        $className = $this->toClassName($baseName);
        $fqcn = $namespace . '\\' . $className;

        $subDir = match ($namespace) {
            'VISU\\Generated\\Scenes' => 'Scenes',
            'VISU\\Generated\\Prefabs' => 'Prefabs',
            default => 'Scenes',
        };

        $phpFile = $this->transpiledDir . '/' . $subDir . '/' . $className . '.php';

        if (!file_exists($phpFile)) {
            return null;
        }

        if (!class_exists($fqcn, false)) {
            require_once $phpFile;
        }

        if (!class_exists($fqcn, false)) {
            return null;
        }

        return $fqcn;
    }

    /**
     * Converts a file basename like "office_level1" to PascalCase "OfficeLevel1".
     */
    private function toClassName(string $baseName): string
    {
        $cleaned = preg_replace('/[^a-zA-Z0-9]+/', ' ', $baseName) ?? $baseName;
        return str_replace(' ', '', ucwords($cleaned));
    }
}
