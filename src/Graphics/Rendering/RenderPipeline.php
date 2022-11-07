<?php

namespace VISU\Graphics\Rendering;

use VISU\Graphics\Exception\PipelineContainerException;
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
        $backbuffer = $data->create(BackbufferData::class);
        $backbuffer->target = $this->importRenderTarget($this->resources->backbuffer);
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
     * @return T
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
     * @return RenderTargetResource 
     */
    public function createRenderTarget(string $resourceName, int $width, int $height): RenderTargetResource
    {
        $resource = $this->createResource(RenderTargetResource::class, $resourceName);
        $resource->width = $width;
        $resource->height = $height;
        
        return $resource;
    }

    public function addPass(RenderPass $pass): void
    {
        $this->passes[] = $pass;
        
        // run render pass setup
        $pass->setup($this, $this->data);
    }

    public function execute(): void
    {
        // attach the backbuffer to the pipeline resources
        $this->resources->backbuffer = $backbuffer;

        foreach ($this->passes as $pass) {
            $pass->execute($this->data, $this->resourceAllocator);
        }
    }
}
