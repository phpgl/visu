<?php

namespace VISU\Graphics\Rendering\Pass;

use Closure;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;

class CallbackPass extends RenderPass
{
    public function __construct(
        private string $name, // the name of the pass for identification (profiling, debugging, etc.)
        private Closure $setupCallback,
        private Closure $executeCallback,
    )
    {
    }   

    /**
     * Returns the name of the render pass, if not overriden this will return the class name.
     */
    public function name() : string 
    {
        return $this->name;
    }

    /**
     * Executes the render pass
     */
    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        ($this->setupCallback)($this, $pipeline, $data);
    }

    /**
     * Executes the render pass
     */
    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        ($this->executeCallback)($data, $resources);
    }
}
