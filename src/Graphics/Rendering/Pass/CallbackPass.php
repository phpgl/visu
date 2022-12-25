<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;

class CallbackPass extends RenderPass
{
    public function __construct(
        private callable $setupCallback,
        private callable $executeCallback,
    )
    {
    }   

    /**
     * Executes the render pass
     */
    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        ($this->setupCallback)($pipeline, $data);
    }

    /**
     * Executes the render pass
     */
    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        ($this->executeCallback)($data, $resources);
    }
}
