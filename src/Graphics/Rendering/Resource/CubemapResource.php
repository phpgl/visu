<?php

namespace VISU\Graphics\Rendering\Resource;

use VISU\Graphics\Rendering\RenderResource;
use VISU\Graphics\TextureOptions;

class CubemapResource extends RenderResource
{   
    /**
     * Resource constructor.
     *
     * @param int $handle the resource handle unique inside of the pipeline.
     * @param string $name A globally unique name to identify the resource
     * @param int $size The size of one edge of the cubemap (ervery face is size x size)
     */
    public function __construct(
        int $handle,
        string $name,
        public readonly int $size,
        public ?TextureOptions $options = null
    )
    {
        parent::__construct($handle, $name);
    }
}