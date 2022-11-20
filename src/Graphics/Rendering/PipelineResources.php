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
     * @var array<int, RenderTarget>
     */
    private array $renderTargets = [];

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
