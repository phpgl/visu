<?php

namespace VISU\Graphics\Rendering\Pass;

use GL\Math\Mat4;
use GL\Math\Vec2;
use VISU\Graphics\Camera;
use VISU\Graphics\Rendering\RenderResource;

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
     * Constructor
     */
    public function __construct(
        Camera $frameCamera,
        Camera $renderCamera,
        Mat4 $projection,
        Mat4 $view,
        int $resolutionX,
        int $resolutionY,
        float $contentScaleX,
        float $contentScaleY
    )
    {
        $this->frameCamera = $frameCamera;
        $this->renderCamera = $renderCamera;
        $this->projection = $projection;
        $this->view = $view;
        $this->resolutionX = $resolutionX;
        $this->resolutionY = $resolutionY;
        $this->contentScaleX = $contentScaleX;
        $this->contentScaleY = $contentScaleY;
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
