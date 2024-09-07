<?php

namespace VISU\Graphics\Rendering\Pass;

use GL\Math\Mat4;
use GL\Math\Vec2;
use VISU\Geo\Frustum;
use VISU\Graphics\Camera;
use VISU\Graphics\Viewport;

/**
 * The camera data object is meant to hold all meta data about the current frame
 * This includes:
 *  - projection & view matrix and their combination as well as the inverse
 *  - frame delta time
 *  - screen resolution, effective resolution and content scale
 *  - frustum of the current frame
 *  - viewport information
 *  - The current camera object used
 */
class CameraData
{
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

    /**
     * Current projection matrix
     */
    public readonly Mat4 $projection;

    /**
     * Current view matrix
     */
    public readonly Mat4 $view;

    /**
     * Projection view matrix
     */
    public readonly Mat4 $projectionView;

    /**
     * Inverse projection view matrix
     */
    public readonly Mat4 $inverseProjectionView;

    /**
     * Frustum of the current frame
     */
    public readonly Frustum $frustum;

    /**
     * Compensation / alpha value for the current frame
     */
    public readonly float $compensation;

    /**
     * Current rendering resolution (X / width)
     */
    public readonly int $resolutionX;

    /**
     * Current rendering resolution (Y / height)
     */
    public readonly int $resolutionY;

    /**
     * Content scale factor (X)
     */
    public readonly float $contentScaleX;

    /**
     * Content scale factor (Y)
     */
    public readonly float $contentScaleY;

    /**
     * Viewport instance for orthographic projections
     */
    public readonly ?Viewport $viewport;

    /**
     * Constructor
     */
    public function __construct(
        Camera $frameCamera,
        Camera $renderCamera,
        Mat4 $projection,
        Mat4 $view,
        Mat4 $projectionView,
        Mat4 $inverseProjectionView,
        Frustum $frustum,
        float $compensation,
        int $resolutionX,
        int $resolutionY,
        float $contentScaleX,
        float $contentScaleY,
        ?Viewport $viewport = null
    )
    {
        $this->frameCamera = $frameCamera;
        $this->renderCamera = $renderCamera;
        $this->projection = $projection;
        $this->view = $view;
        $this->projectionView = $projectionView;
        $this->inverseProjectionView = $inverseProjectionView;
        $this->frustum = $frustum;
        $this->compensation = $compensation;
        $this->resolutionX = $resolutionX;
        $this->resolutionY = $resolutionY;
        $this->contentScaleX = $contentScaleX;
        $this->contentScaleY = $contentScaleY;
        $this->viewport = $viewport;
    }

    /**
     * Returns the resolution in real pixels (not scaled)
     */
    public function getDeviceResolutionVec(): Vec2
    {
        return new Vec2($this->resolutionX, $this->resolutionY);
    }

    /**
     * Returns the resolution in scaled pixels
     */
    public function getResolutionVec(): Vec2
    {
        return new Vec2($this->resolutionX / $this->contentScaleX, $this->resolutionY / $this->contentScaleY);
    }
}
