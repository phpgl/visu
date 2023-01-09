<?php

namespace VISU\Component\VISULowPoly;

use VISU\System\VISULowPoly\LPModel;

class DynamicRenderableModel
{
    /**
     * The model that is being rendered
     */
    public LPModel $model;

    /**
     * Should this object cast shadows?
     */
    public bool $castsShadows = true;
}