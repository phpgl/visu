<?php

namespace VISU\Graphics\Rendering;

class RenderContext
{
    /**
     * Also named lag or deltaTime, this represents where we are inbetween frames
     * and is used to interpolate motion for smooth visuals even with 
     * inconsistant frame rates.
     * 
     * @var float
     */
    public readonly float $compensation;

    /**
     * Rendering pipeline instance used to render the scene
     */
    public readonly RenderPipeline $pipeline;

    /**
     * Rendering Pipline data container, used to store data between systems
     * and render passes
     */
    public readonly PipelineContainer $data;

    /**
     * Rendering pipeline resources, used to store resources between systems
     * and render passes
     */
    public readonly PipelineResources $resources;

    /**
     * Constructor
     */
    public function __construct(
        RenderPipeline $pipeline,
        PipelineContainer $data,
        PipelineResources $resources,
        float $compensation = 0.0
    )
    {
        $this->pipeline = $pipeline;
        $this->data = $data;
        $this->resources = $resources;
        $this->compensation = $compensation;
    }
}
