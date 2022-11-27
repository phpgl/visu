<?php

namespace VISU\Graphics\Rendering;

use VISU\Graphics\Exception\PipelineResourceException;
use VISU\Graphics\GLState;
use VISU\Graphics\RenderTarget;

class PipelineResources
{
    /**
     * Internal array of render targets
     * 
     * @var array<string, RenderTarget>
     */
    private array $renderTargets = [];

    /**
     * Internal array of textures
     * 
     * @var array<string, Texture>
     */
    private array $textures = [];

    /**
     * Internal array of buffers
     * 
     * @var array<string, Buffer>
     */
    private array $buffers = [];

    /**
     * Holder of mixed generic static resources 
     * 
     * @var array<string, mixed>
     */
    private array $staticStorage = [];

    /**
     * The current ticke index the resources are accessed with
     * 
     * @var int
     */
    private int $tickIndex = 0;
    
    /**
     * A map storing the tick each resource was last accessed
     * 
     * @var array
     */
    private array $resourceUseTick = [];

    /**
     * Constructor
     * 
     * @param GLState $glState 
     * @return void 
     */
    public function __construct(
        private GLState $glState
    )
    {
    }

    /**
     * Sets the current tick index
     * 
     * @param int $index
     * @return void
     */
    public function setCurrentTick(int $index): void
    {
        $this->tickIndex = $index;
    }

    /**
     * Sets a render target to the given handle
     * 
     * @param RenderResource $resource
     * @param RenderTarget $target
     * 
     * @return void
     */
    public function setRenderTarget(RenderResource $resource, RenderTarget $target): void
    {
        $this->renderTargets[$resource->handle] = $target;
    }

    /**
     * Returns the render target for the given resource
     * 
     * @param RenderResource $resource
     * 
     * @return RenderTarget
     */
    public function getRenderTarget(RenderResource $resource): RenderTarget
    {
        $this->resourceUseTick[$resource->name] = $this->tickIndex;

        if (!isset($this->renderTargets[$resource->handle])) {
            throw new PipelineResourceException("Render target not found for resource handle: " . $resource->handle . ' name: ' . $resource->name);
        }

        return $this->renderTargets[$resource->handle];
    }

    /**
     * Stores a generic resource / value or object by name
     * 
     * @param string $name
     * @param mixed $value
     */
    public function setStaticResource(string $name, mixed $value): void
    {
        $this->staticStorage[$name] = $value;
    }

    /**
     * Returns a generic resource / value or object by name
     * 
     * @param string $name
     * 
     * @return mixed
     */
    public function getStaticResource(string $name): mixed
    {
        if (!isset($this->staticStorage[$name])) {
            throw new PipelineResourceException("Static resource not found for name: " . $name);
        }

        return $this->staticStorage[$name];
    }

    /**
     * Cacehs a generic resource / value or object by name
     * This is the same as getStaticResource but you provide a callback which is called if the resource is not found.
     * 
     * @param string $name
     * @param callable $callback
     * 
     * @return mixed
     */
    public function cacheStaticResource(string $name, callable $callback): mixed
    {
        if (!isset($this->staticStorage[$name])) {
            $this->staticStorage[$name] = $callback($this->glState);
        }

        return $this->staticStorage[$name];
    }

    /**
     * Collects all garbage and removes all unused resources
     * 
     * @return void
     */
    public function collectGarbage(): void
    {
        foreach ($this->resourceUseTick as $name => $tick) {
            if ($tick < $this->tickIndex) {
                unset($this->renderTargets[$name]);
                unset($this->resourceUseTick[$name]);
            }
        }
    }
}
