<?php

namespace VISU\Graphics\Rendering;

use VISU\Graphics\Rendering\Pass\BackbufferData;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\RenderTarget;

class RenderPipeline
{
    /**
     * An array of render passes
     *
     * @var array<RenderPass>
     */
    private array $passes = [];

    /**
     * An array of render resources
     * 
     * @var array<RenderResource>
     */
    private array $resources = [];

    /**
     * A internal counter for the next resource handle
     * 
     * @var int
     */
    private int $resourceHandleIndex = 0;

    /**
     * Constrcutor
     */
    public function __construct(
        private PipelineResources $resourceAllocator,
        private PipelineContainer $data,
        private RenderTarget $backbuffer
    )
    {
        $backbufferData = $data->create(BackbufferData::class);
        $backbufferData->target = $this->importRenderTarget('backbuffer', $backbuffer);
    }

    /**
     * Returns a new resource handle
     * 
     * @return int
     */
    private function nextResourceHandle(): int
    {
        return $this->resourceHandleIndex++;
    }

    /**
     * Creates a new render resource of given type with name
     * 
     * @template T of RenderResource
     * @param class-string<T> $type
     * @param string $resourceName
     * 
     * @return T|RenderResource
     */
    private function createResource(string $type, string $resourceName): RenderResource
    {
        $handle = $this->nextResourceHandle();
        $this->resources[$handle] = new $type($handle, $resourceName);
        return $this->resources[$handle];
    }

    /**
     * Creates a new render target resource
     * 
     * @param string $resourceName 
     * @param int $width 
     * @param int $height 
     * 
     * @return RenderTargetResource 
     */
    public function createRenderTarget(string $resourceName, int $width, int $height): RenderTargetResource
    {
        /** @var RenderTargetResource */
        $resource = $this->createResource(RenderTargetResource::class, $resourceName);
        $resource->width = $width;
        $resource->height = $height;
        
        return $resource;
    }

    /**
     * Imports a render target resource
     * 
     * @param string $resourceName 
     * @param RenderTarget $target 
     * 
     * @return RenderTargetResource 
     */
    public function importRenderTarget(string $resourceName, RenderTarget $target): RenderTargetResource
    {
        /** @var RenderTargetResource */
        $resource = $this->createResource(RenderTargetResource::class, $resourceName);
        $resource->width = $target->width();
        $resource->height = $target->height();

        $this->resourceAllocator->setRenderTarget($resource, $target);

        return $resource;
    }

    /**
     * Adds a new render pass to the pipeline
     * 
     * @param RenderPass $pass
     */
    public function addPass(RenderPass $pass): void
    {
        $this->passes[] = $pass;
        
        // run render pass setup
        $pass->setup($this, $this->data);
    }

    /**
     * Execute the render pipeline
     * 
     * @param int $tickIndex Lets the pipeline know which tick it is.
     *                       This is used to determine the order in which this pipeline is executed.
     *                       The paramter is rather important as for example the garbage collector
     *                       uses this to determine when to free resources.
     * @return void 
     */
    public function execute(int $tickIndex): void
    {
        $this->resourceAllocator->setCurrentTick($tickIndex);

        foreach ($this->passes as $pass) {
            $pass->execute($this->data, $this->resourceAllocator);
        }

        $this->resourceAllocator->collectGarbage();
    }
}
