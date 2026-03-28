<?php

namespace VISU\Asset;

use VISU\Graphics\Texture;
use VISU\Graphics\TextureOptions;

interface AssetManagerInterface
{
    /**
     * Resolves a relative asset path to an absolute path.
     */
    public function resolvePath(string $relativePath): string;

    /**
     * Loads and caches a texture from a file path (relative to basePath).
     */
    public function loadTexture(string $path, ?TextureOptions $options = null): Texture;

    /**
     * Returns a previously loaded texture or null.
     */
    public function getTexture(string $path): ?Texture;

    /**
     * Checks if a texture is already cached.
     */
    public function hasTexture(string $path): bool;

    /**
     * Loads and caches a JSON file (relative to basePath).
     *
     * @return array<mixed>
     */
    public function loadJson(string $path): array;

    /**
     * Removes a texture from the cache.
     */
    public function unloadTexture(string $path): void;

    /**
     * Removes a JSON file from the cache.
     */
    public function unloadJson(string $path): void;

    /**
     * Clears all cached assets.
     */
    public function clear(): void;

    /**
     * Returns the base path.
     */
    public function getBasePath(): string;
}
