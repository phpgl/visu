<?php

namespace VISU\Graphics\Rendering\Renderer;

use Closure;
use GL\VectorGraphics\VGContext;
use VISU\FlyUI\FlyUI;
use VISU\Graphics\Rendering\Pass\FlyUIPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\TextureResource;
use VISU\OS\Input;
use VISU\Signal\Dispatcher;

class FlyUIRenderer
{   
    /**
     * Constructor 
     */
    public function __construct(
        private VGContext $vg,
        private Dispatcher $dispatcher,
        private Input $input,
    )
    {
        FlyUI::initailize($vg, $dispatcher, $input);
        FlyUI::$instance->setSelfManageVGContext(true);
    }

    /**
     * Attaches a render pass to the pipeline
     * 
     * @param RenderPipeline $pipeline 
     * @param RenderTargetResource $renderTarget
     * @param TextureResource $depthTexture
     */
    public function attachPass(
        RenderPipeline $pipeline, 
        RenderTargetResource $renderTarget,
        Closure $uiRenderCallback
    ) : void
    {
        $pipeline->addPass(new FlyUIPass(
            $renderTarget,
            $uiRenderCallback
        ));
    }
}
