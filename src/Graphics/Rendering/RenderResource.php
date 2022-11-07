<?php

namespace VISU\Graphics\Rendering;

class RenderResource
{
    /**
     * Resource constructor.
     *
     * @param int $handle the resource handle unique inside of the pipeline.
     * @param string $name A unique name to identify the resource
     */
    public function __construct(
        public readonly int $handle,
        public readonly string $name,
    )
    {
        
    }
}
