<?php

namespace VISU\Graphics\Rendering\Pass;

use GL\Math\Mat4;
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
     * Constructor
     */
    public function __construct(
        Camera $frameCamera,
        Camera $renderCamera,
        Mat4 $projection,
        Mat4 $view,
    )
    {
        $this->frameCamera = $frameCamera;
        $this->renderCamera = $renderCamera;
        $this->projection = $projection;
        $this->view = $view;
    }
}
