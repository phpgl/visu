<?php

namespace VISU\Graphics\Rendering\Resource;

use VISU\Graphics\Rendering\RenderResource;
use VISU\Graphics\TextureOptions;

class TextureResource extends RenderResource
{   
    /**
     * Resource constructor.
     *
     * @param int $handle the resource handle unique inside of the pipeline.
     * @param string $name A globally unique name to identify the resource
     */
    public function __construct(
        int $handle,
        string $name,
        public readonly int $width,
        public readonly int $height,
        public ?TextureOptions $options = null
    )
    {
        parent::__construct($handle, $name);
    }
}
