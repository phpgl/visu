<?php

namespace VISU\Component\VISULowPoly;

/**
 * A static low poly model component.
 * 
 * Static as in non-moving/changing over time, e.g. for static world geometry.
 * You can still destory and construct them during the runtime, but this will come at
 * a significant performance cost as the rendering system has to re-build its internal data strucutres. 
 */
class LPStaticModel
{
    /**
     * Construct a Static Low Poly Model
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