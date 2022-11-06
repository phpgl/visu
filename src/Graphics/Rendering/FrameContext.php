<?php

namespace VISU\Graphics\Rendering;

use GL\Math\Vec2;
use VISU\Graphics\Camera;

class FrameContext
{
    /**
     * Also named lag, this represents where we are inbetween frames
     * and is used to interpolate motion for smooth visuals even with 
     * inconsistant frame rates.
     * 
     * @var float
     */
    public readonly float $compensation;

    /**
     * The frames target resolution width 
     * 
     * @var int
     */
    public readonly int $resolutionWidth;

    /**
     * The frames target resolution height 
     * 
     * @var int
     */
    public readonly int $resolutionHeight;

    /**
     * The source camera (main_camera)
     * this is the camera that can be modified by the scene / game
     */
    public readonly Camera $sourceCamera;

    /**
     * The interpolarated camera of the scene
     * this camera is used for things like frustum culling or LOD levels
     */
    public readonly Camera $frameCamera;

    /**
     * The camera used to actually render the scene
     * espacially usefull for debugging allowing to view the scene
     * from an outside perspective. This can be used to debug LOD or culling.
     */
    public readonly Camera $renderCamera;
}
