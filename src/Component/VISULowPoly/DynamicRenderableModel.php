<?php

namespace VISU\Component\VISULowPoly;

use VISU\System\VISULowPoly\LPModel;

class DynamicRenderableModel
{
    /**
     * The name of the model to render
     */
    public string $modelIdentifier;

    /**
     * Should this object cast shadows?
     */
    public bool $castsShadows = true;
}