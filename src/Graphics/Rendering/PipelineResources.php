<?php

namespace VISU\Graphics\Rendering;

use VISU\Graphics\GLState;
use VISU\Graphics\RenderTarget;

class PipelineResources
{
    

    public function __construct(
        private GLState $glState
    )
    {
    }
}
