<?php

namespace VISU\Graphics;

use GL\Math\Mat4;
use GL\Math\Quat;
use GL\Math\Vec2;
use GL\Math\Vec3;
use GL\Math\Vec4;
use VISU\Geo\Ray;
use VISU\Geo\Transform;

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
        else {
            $this->projectionMatrixAlloc->ortho(
                0.0,
                $renderTarget->width() / $renderTarget->contentScaleX,
                $renderTarget->height() / $renderTarget->contentScaleY,
                0.0,
                $this->nearPlane, 
                $this->farPlane
            );
        }

        return $this->projectionMatrixAlloc->copy();
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
        if ($this->allowInterpolation) {
            $this->interpolationTransformAlloc->position = Vec3::mix($this->lfCameraPos, $this->transform->position, $deltaTime);
            $this->interpolationTransformAlloc->orientation = Quat::slerp($this->lfCameraRot, $this->transform->orientation, $deltaTime);
            $this->interpolationTransformAlloc->markDirty();
    
            return Mat4::inverted($this->interpolationTransformAlloc->getLocalMatrix());
        }

        return Mat4::inverted($this->transform->getLocalMatrix());
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
}
