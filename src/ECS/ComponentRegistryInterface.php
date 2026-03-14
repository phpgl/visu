<?php

namespace VISU\ECS;

interface ComponentRegistryInterface
{
    /**
     * Registers a component class with a short type name.
     *
     * @param class-string $className
     */
    public function register(string $typeName, string $className): void;

    /**
     * Resolves a short type name to a fully qualified class name.
     *
     * @return class-string
     */
    public function resolve(string $typeName): string;

    /**
     * Returns whether a type name is registered.
     */
    public function has(string $typeName): bool;

    /**
     * Creates a component instance from a type name and optional property array.
     *
     * @param array<string, mixed> $properties
     */
    public function create(string $typeName, array $properties = []): object;

    /**
     * Returns the reverse lookup: class name to type name.
     *
     * @param class-string $className
     */
    public function getTypeName(string $className): ?string;

    /**
     * Returns all registered type names.
     *
     * @return array<string>
     */
    public function getRegisteredTypes(): array;

    /**
     * Returns the full type map.
     *
     * @return array<string, class-string>
     */
    public function getTypeMap(): array;
}
