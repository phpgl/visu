<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\Graphics\Cubemap;
use VISU\Graphics\Texture;

/**
 * Cache for Image-Based Lighting (IBL) precomputed textures.
 * 
 * This cache tracks whether the IBL maps need to be recomputed.
 * The actual textures are managed by PipelineResources; this class
 * just stores the source cubemap ID to detect when recomputation is needed.
 */
class IBLCache
{
    /**
     * the GL texture ID of the source cubemap this cache was computed from
     */
    private int $sourceCubemapId = 0;

    /**
     * whether the cache has been populated
     */
    private bool $isValid = false;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Checks if the cache is valid for the given source cubemap
     * 
     * @param Cubemap $sourceCubemap The environment cubemap to check against
     * @return bool True if the cache is valid and can be used
     */
    public function isValidFor(Cubemap $sourceCubemap): bool
    {
        return $this->isValid && $this->sourceCubemapId === $sourceCubemap->id;
    }

    /**
     * Invalidates the cache, forcing a recompute on next use
     */
    public function invalidate(): void
    {
        $this->isValid = false;
        $this->sourceCubemapId = 0;
    }

    /**
     * Marks the cache as valid for the given source cubemap
     * 
     * @param Cubemap $sourceCubemap The environment cubemap this cache was computed from
     */
    public function markValid(Cubemap $sourceCubemap): void
    {
        $this->sourceCubemapId = $sourceCubemap->id;
        $this->isValid = true;
    }
}
