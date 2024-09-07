<?php

namespace VISU\Graphics;

use GL\Math\GLM;
use GL\Math\Mat4;
use GL\Math\Quat;
use GL\Math\Vec2;
use GL\Math\Vec3;
use GL\Math\Vec4;
use GL\VectorGraphics\VGContext;
use VISU\Exception\VISUException;
use VISU\Geo\Frustum;
use VISU\Geo\Ray;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\Pass\CameraData;

class Camera
{
    /**
     * The cameras projection mode
     */
    public CameraProjectionMode $projectionMode;

    /**
     * The camera's transform / location, orientation in space
     */
    public Transform $transform;

    /**
     * Holds the cameras position from the last frame
     */
    private Vec3 $lfCameraPos;

    /**
     * Holds the cameras orientation from the last frame
     */
    private Quat $lfCameraRot;

    /**
     * The cameras near plane distance
     * This is the distance from the camera to the near clipping plane, aka 
     * the closest distance at which objects will be rendered.
     */
    public float $nearPlane = 0.1;

    /**
     * The cameras far plane distance
     * This is the distance from the camera to the far clipping plane, aka
     * the furthest distance at which objects will be rendered.
     */
    public float $farPlane = 65536.0;

    /**
     * The cameras field of view (FOV)
     * 
     * @var float
     */
    public float $fieldOfView = 45.0;

    /**
     * Fixed world height in units for orthographic static world projection mode
     * 
     * @var float
     */
    public float $staticWorldHeight = 100.0;

    /**
     * Camera Zoom
     * 
     * Only applies to orthographic projection modes and vg contexts.
     */
    public float $zoom = 1.0;

    /**
     * Flip the viewport on the y axis
     * The camera by default uses y+ as up, this will flip the viewport to use y- as up.
     * 
     * When you want to use the camera with a vector graphics context you will want to
     * flip the viewport.
     */
    public bool $flipViewportY = false;

    /**
     * Internal holder for the projection view matrix
     */
    private Mat4 $projectionMatrixAlloc;

    /**
     * Internal holder for a temporary transform instance used for interpolation
     */
    private Transform $interpolationTransformAlloc;

    /**
     * If enabled, the camera will interpolate its position and orientation
     * from the previous frame to the current frame given the delta time.
     * 
     * When enabled you will have to call `finalizeFrame` at the end of each frame.
     * 
     * @var bool
     */
    public bool $allowInterpolation = true;

    /**
     * Camera constructor.
     * 
     * @param CameraProjectionMode $mode The cameras projection mode
     * @param Transform|null $transform The inital cameras transform, note this is by reference! (Its PHP..)
     */
    public function __construct(CameraProjectionMode $mode, Transform $transform = null)
    {
        $this->projectionMode = $mode;
        $this->transform = $transform ?? new Transform;
        $this->lfCameraPos = $this->transform->position->copy();
        $this->lfCameraRot = $this->transform->orientation->copy();

        // allocator vars
        $this->projectionMatrixAlloc = new Mat4;
        $this->interpolationTransformAlloc = new Transform;
    }

    /**
     * Calculates and retuns the cameras projection matrix.
     * 
     * @param RenderTarget $renderTarget The render target to calculate the projection matrix for
     * @return Mat4 A copy of the calculated projection matrix
     */
    public function getProjectionMatrix(RenderTarget $renderTarget) : Mat4
    {   
        if ($this->projectionMode === CameraProjectionMode::perspective) {
            $this->projectionMatrixAlloc->perspective(
                $this->fieldOfView, 
                $renderTarget->width() / $renderTarget->height(), 
                $this->nearPlane, 
                $this->farPlane
            );
        }
        elseif ($this->projectionMode === CameraProjectionMode::orthographicScreen) {
            $this->projectionMatrixAlloc->ortho(
                0.0,
                $renderTarget->width() / $renderTarget->contentScaleX,
                $renderTarget->height() / $renderTarget->contentScaleY,
                0.0,
                $this->nearPlane, 
                $this->farPlane
            );
        }
        elseif ($this->projectionMode === CameraProjectionMode::orthographicWorld) {
            $halfWidth = ($renderTarget->width() / $renderTarget->contentScaleX) * 0.5;
            $halfHeight = ($renderTarget->height() / $renderTarget->contentScaleY) * 0.5;
            if ($this->flipViewportY) $halfHeight = -$halfHeight;

            $this->projectionMatrixAlloc->ortho(
                -$halfWidth,
                $halfWidth,
                -$halfHeight,
                $halfHeight,
                $this->nearPlane, 
                $this->farPlane
            );
        }
        elseif ($this->projectionMode === CameraProjectionMode::orthographicStaticWorld) {
            $halfHeight = $this->staticWorldHeight * 0.5;
            $aspectRatio = $renderTarget->width() / $renderTarget->height();
            $halfWidth = $halfHeight * $aspectRatio;
            if ($this->flipViewportY) $halfHeight = -$halfHeight;
            
            $this->projectionMatrixAlloc->ortho(
                -$halfWidth,
                $halfWidth,
                -$halfHeight,
                $halfHeight,
                $this->nearPlane, 
                $this->farPlane
            );
        }
        else {
            throw new VISUException('Unknown or unsupported projection mode given');
        }

        return $this->projectionMatrixAlloc->copy();
    }

    /**
     * Returns a viewport for the given render target
     * This will only work for orthographic projection modes.
     * 
     * @param RenderTarget $renderTarget The render target to calculate the viewport for
     * @return Viewport The calculated viewport
     */
    public function getViewport(RenderTarget $renderTarget) : Viewport
    {
        $screenSpaceWidth = $renderTarget->effectiveWidth();
        $screenSpaceHeight = $renderTarget->effectiveHeight();

        if ($this->projectionMode === CameraProjectionMode::orthographicScreen) {
            return new Viewport(
                0.0,
                $screenSpaceWidth,
                $screenSpaceHeight,
                0.0,
                $screenSpaceWidth,
                $screenSpaceHeight,
            );
        }
        elseif ($this->projectionMode === CameraProjectionMode::orthographicWorld) {
            $halfWidth = $screenSpaceWidth * 0.5;
            $halfHeight = $screenSpaceHeight * 0.5;
            if ($this->flipViewportY) $halfHeight = -$halfHeight;
            return new Viewport(
                -$halfWidth,
                $halfWidth,
                -$halfHeight,
                $halfHeight,
                $screenSpaceWidth,
                $screenSpaceHeight,
            );
        }
        elseif ($this->projectionMode === CameraProjectionMode::orthographicStaticWorld) {
            $halfHeight = $this->staticWorldHeight * 0.5;
            $aspectRatio = $renderTarget->width() / $renderTarget->height();
            $halfWidth = $halfHeight * $aspectRatio;
            if ($this->flipViewportY) $halfHeight = -$halfHeight;
            return new Viewport(
                -$halfWidth,
                $halfWidth,
                -$halfHeight,
                $halfHeight,
                $screenSpaceWidth,
                $screenSpaceHeight,
            );
        }
        else {
            throw new VISUException('Unknown or unsupported projection mode given');
        }
    }

    /**
     * Returns the model matrix of the camera
     * This is basically the inverse of the cameras view matrix.
     * 
     * @return Mat4 The model matrix of the camera
     */
    public function getModelMatrix(float $deltaTime = 0.0) : Mat4
    {
        if ($this->allowInterpolation) {
            $this->interpolationTransformAlloc->position = Vec3::mix($this->lfCameraPos, $this->transform->position, $deltaTime);
            $this->interpolationTransformAlloc->orientation = Quat::slerp($this->lfCameraRot, $this->transform->orientation, $deltaTime);
            $this->interpolationTransformAlloc->markDirty();
    
            return $this->interpolationTransformAlloc->getLocalMatrix();
        }

        return $this->transform->getLocalMatrix();
    }

    /**
     * Calculates and returns the cameras view matrix. The matrix is inverted on the fly 
     * so cache the result if you need to use it multiple times.
     * 
     * @param float $deltaTime The delta time since the last frame
     * @return Mat4 A copy of the calculated view matrix
     */
    public function getViewMatrix(float $deltaTime = 0.0) : Mat4
    {
        return Mat4::inverted($this->getModelMatrix($deltaTime));
    }

    /**
     * Calculates and returns the cameras view projection matrix.
     */
    public function getViewProjectionMatrix(RenderTarget $renderTarget, float $deltaTime = 0.0) : Mat4
    {
        return $this->getProjectionMatrix($renderTarget) * $this->getViewMatrix($deltaTime);
    }

    /**
     * Calculates and returns the ray direction of the camera at the given screen position.
     * Warning: This method is rather expensive, use it sparingly.
     */
    public function getSSDirection(RenderTarget $renderTarget, Vec2 $screenPos) : Vec3
    {
        $invVP = Mat4::inverted($this->getViewProjectionMatrix($renderTarget));
        $p = $invVP * new Vec4($screenPos->x, -$screenPos->y, 1.0, 1.0);

        $p->x = $p->x / $p->w;
        $p->y = $p->y / $p->w;
        $p->z = $p->z / $p->w;

        return Vec3::normalized(new Vec3($p->x, $p->y, $p->z) - $this->transform->position);
    }

    /**
     * Creates a Ray instance from the camera at the given screen position.
     * Warning: This method is rather expensive, use it sparingly.
     */
    public function getSSRay(RenderTarget $renderTarget, Vec2 $screenPos) : Ray
    {
        return new Ray($this->transform->position, $this->getSSDirection($renderTarget, $screenPos));
    }

    /**
     * Returns a screen space position from the given world space position.
     */
    public function getSSPointFromWS(Viewport $viewport, Vec2 $viewSpacePos) : Vec2
    {
        return $viewport->viewSpaceToScreenSpace(new Vec2(
            $viewSpacePos->x - $this->transform->position->x,
            $viewSpacePos->y - $this->transform->position->y
        ));
    }

    /**
     * Returns a world space position from the given screen space position.
     */
    public function getWSPointFromSS(Viewport $viewport, Vec2 $screenSpacePos) : Vec2
    {
        return $viewport->screenSpaceToViewSpace($screenSpacePos) + new Vec2(
            $this->transform->position->x,
            $this->transform->position->y
        );
    }

    /**
     * Will translate and scale the given VGContext to match the cameras viewport.
     */
    public function transformVGSpace(Viewport $viewport, VGContext $vg) : void
    {
        $scaleFactorX = $viewport->screenSpaceWidth / $viewport->width;
        $scaleFactorY = $viewport->screenSpaceHeight / $viewport->height;    

        $vg->scale($scaleFactorX, $scaleFactorY);
    
        $offsetX = -$viewport->left;
        $offsetY = -$viewport->top;
        $offsetX -= $this->transform->position->x;
        $offsetY -= $this->transform->position->y;

        $vg->translate($offsetX, $offsetY);
        $vg->scale($this->zoom, $this->zoom);
    }


    /**
     * Calling this method will store the current camera position and orientation
     * as the previous frame. This is used for interpolation.
     * 
     * @return void 
     */
    public function finalizeFrame()
    {
        $this->lfCameraPos = $this->transform->position->copy();
        $this->lfCameraRot = $this->transform->orientation->copy();
    }

    /**
     * Creates and returns a camera data instance for the given render target and delta time.
     */
    public function createCameraData(RenderTarget $renderTarget, float $compensation = 0.0) : CameraData
    {
        // extract the camera view and projection matrices
        $viewMatrix = $this->getViewMatrix($compensation); // <- this is interpolated
        $projectionMatrix = $this->getProjectionMatrix($renderTarget);

        /** @var Mat4 */
        $projectionViewMatrix = $projectionMatrix * $viewMatrix;
        $inverseProjectionViewMatrix = Mat4::inverted($projectionViewMatrix);

        return new CameraData(
            frameCamera: $this,
            renderCamera: $this,
            projection: $projectionMatrix,
            view: $viewMatrix,
            projectionView: $projectionViewMatrix,
            inverseProjectionView: $inverseProjectionViewMatrix,
            frustum: Frustum::fromMat4($projectionViewMatrix),
            compensation: $compensation,
            resolutionX: $renderTarget->width(),
            resolutionY: $renderTarget->height(),
            contentScaleX: $renderTarget->contentScaleX,
            contentScaleY: $renderTarget->contentScaleY,
            viewport: $this->projectionMode !== CameraProjectionMode::perspective ? $this->getViewport($renderTarget) : null,
        );
    }
}
