<?php

namespace VISU\ECS;

use VISU\ECS\Exception\ECSException;

class ComponentRegistry implements ComponentRegistryInterface
{
    /**
     * Map of component type string to fully qualified class name.
     * e.g. "SpriteRenderer" => "VISU\Component\SpriteRenderer"
     *
     * @var array<string, class-string>
     */
    private array $typeMap = [];

    /**
     * Registers a component class with a short type name.
     *
     * @param class-string $className
     */
    public function register(string $typeName, string $className): void
    {
        if (!class_exists($className)) {
            throw new ECSException("Component class does not exist: {$className}");
        }

        $this->typeMap[$typeName] = $className;
    }

    /**
     * Resolves a short type name to a fully qualified class name.
     *
     * @return class-string
     */
    public function resolve(string $typeName): string
    {
        if (!isset($this->typeMap[$typeName])) {
            throw new ECSException("Unknown component type: '{$typeName}'. Did you forget to register it?");
        }

        return $this->typeMap[$typeName];
    }

    /**
     * Returns whether a type name is registered.
     */
    public function has(string $typeName): bool
    {
        return isset($this->typeMap[$typeName]);
    }

    /**
     * Creates a component instance from a type name and optional property array.
     *
     * @param array<string, mixed> $properties
     */
    public function create(string $typeName, array $properties = []): object
    {
        $className = $this->resolve($typeName);
        $component = new $className();

        foreach ($properties as $key => $value) {
            if (property_exists($component, $key)) {
                $component->$key = $value;
            }
        }

        return $component;
    }

    /**
     * Returns the reverse lookup: class name to type name.
     *
     * @param class-string $className
     */
    public function getTypeName(string $className): ?string
    {
        $flipped = array_flip($this->typeMap);
        return $flipped[$className] ?? null;
    }

    /**
     * Returns all registered type names.
     *
     * @return array<string>
     */
    public function getRegisteredTypes(): array
    {
        return array_keys($this->typeMap);
    }

    /**
     * Returns the full type map.
     *
     * @return array<string, class-string>
     */
    public function getTypeMap(): array
    {
        return $this->typeMap;
    }
}
