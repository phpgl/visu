<?php

namespace VISU\Graphics\Rendering\Pass;

use Closure;
use VISU\FlyUI\FlyUI;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;

class FlyUIPass extends RenderPass
{
    public function __construct(
        private RenderTargetResource $renderTargetRes,
        private Closure $uiRenderCallback,
    )
    {
    }   

    /**
     * Executes the render pass
     */
    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $pipeline->writes($this, $this->renderTargetRes);
    }

    /**
     * Executes the render pass
     */
    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        $renderTarget = $resources->getRenderTarget($this->renderTargetRes);

        // activate the render target
        $resources->activateRenderTarget($this->renderTargetRes);
        
        // begin a FlyUI frame
        FlyUI::beginFrame($renderTarget->effectiveSizeVec(), $renderTarget->contentScaleX);

        // execute the UI render callback
        ($this->uiRenderCallback)();

        // end the FlyUI frame
        FlyUI::endFrame();

        // reset the GL state as the VGContext might have changed it
        $resources->gl->reset();
    }
}
