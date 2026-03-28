<?php

namespace VISU\Component;

use VISU\Graphics\Material;

class MeshRendererComponent
{
    /**
     * The model identifier as registered in the ModelCollection
     */
    public string $modelIdentifier;

    /**
     * Optional material override (replaces the model's materials)
     */
    public ?Material $materialOverride = null;

    /**
     * Whether this entity casts shadows
     */
    public bool $castsShadows = true;

    /**
     * Whether this entity receives shadows
     */
    public bool $receivesShadows = true;

    public function __construct(string $modelIdentifier)
    {
        $this->modelIdentifier = $modelIdentifier;
    }
}
