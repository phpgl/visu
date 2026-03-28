<?php

namespace VISU\Asset;

use VISU\Graphics\GLState;
use VISU\Graphics\Texture;
use VISU\Graphics\TextureOptions;

class AssetManager implements AssetManagerInterface
{
    /**
     * Loaded textures cache.
     *
     * @var array<string, Texture>
     */
    private array $textures = [];

    /**
     * Loaded JSON data cache.
     *
     * @var array<string, array<mixed>>
     */
    private array $jsonCache = [];

    /**
     * Base path for asset lookups.
     */
    private string $basePath;

    /**
     * GL state for texture creation.
     */
    private GLState $gl;

    public function __construct(GLState $gl, string $basePath)
    {
        $this->gl = $gl;
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Resolves a relative asset path to an absolute path.
     */
    public function resolvePath(string $relativePath): string
    {
        return $this->basePath . '/' . ltrim($relativePath, '/');
    }

    /**
     * Loads and caches a texture from a file path (relative to basePath).
     */
    public function loadTexture(string $path, ?TextureOptions $options = null): Texture
    {
        if (isset($this->textures[$path])) {
            return $this->textures[$path];
        }

        $fullPath = $this->resolvePath($path);

        $texture = new Texture($this->gl, $path);
        $texture->loadFromFile($fullPath, $options);

        $this->textures[$path] = $texture;
        return $texture;
    }

    /**
     * Returns a previously loaded texture or null.
     */
    public function getTexture(string $path): ?Texture
    {
        return $this->textures[$path] ?? null;
    }

    /**
     * Checks if a texture is already cached.
     */
    public function hasTexture(string $path): bool
    {
        return isset($this->textures[$path]);
    }

    /**
     * Loads and caches a JSON file (relative to basePath).
     *
     * @return array<mixed>
     */
    public function loadJson(string $path): array
    {
        if (isset($this->jsonCache[$path])) {
            return $this->jsonCache[$path];
        }

        $fullPath = $this->resolvePath($path);

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("JSON asset not found: {$fullPath}");
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read JSON asset: {$fullPath}");
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON in asset: {$fullPath}");
        }

        $this->jsonCache[$path] = $data;
        return $data;
    }

    /**
     * Removes a texture from the cache (freeing GPU memory).
     */
    public function unloadTexture(string $path): void
    {
        unset($this->textures[$path]);
    }

    /**
     * Removes a JSON file from the cache.
     */
    public function unloadJson(string $path): void
    {
        unset($this->jsonCache[$path]);
    }

    /**
     * Clears all cached assets.
     */
    public function clear(): void
    {
        $this->textures = [];
        $this->jsonCache = [];
    }

    /**
     * Returns the base path.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
