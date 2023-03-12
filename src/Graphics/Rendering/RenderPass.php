<?php

namespace VISU\Graphics\Rendering;

abstract class RenderPass
{
    /**
     * Returns the name of the render pass, if not overriden this will return the class name.
     * @return string 
     */
    public function name() : string {
        return basename(str_replace("\\", '/', get_class($this)));
    }

    /**
     * Build / Setup the render pass
     * 
     * In this step the pass should declare its in and outputs and allocate resources. 
     */
    abstract public function setup(RenderPipeline $pipeline, PipelineContainer $data): void;

    /**
     * This method is called to execute the render pass, actual rendering should happen here.
     * This is where you issue draw calls to the GPU.
     */
    abstract public function execute(PipelineContainer $data, PipelineResources $resources): void;
}
