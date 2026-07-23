<?php

namespace VISU\Component\VISULowPoly;

class LPDynamicModel
{
    /**
     * Construct a new DynamicRenderableModel
     */
    public function __construct(
        /**
         * The name / id of the model as it is registered in the LPModelCollection
         */
        public string $modelIdentifier
    )
    {
    }

    /**
     * Should this object cast shadows?
     */
    public bool $castsShadows = true;
}