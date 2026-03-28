<?php

namespace VISU\Component;

use GL\Math\Vec3;

class Camera3DComponent
{
    /**
     * Current yaw angle in degrees (rotation around Y axis)
     */
    public float $yaw = 0.0;

    /**
     * Current pitch angle in degrees (rotation around X axis)
     */
    public float $pitch = -15.0;

    /**
     * Minimum pitch angle in degrees
     */
    public float $pitchMin = -89.0;

    /**
     * Maximum pitch angle in degrees
     */
    public float $pitchMax = 89.0;

    /**
     * Mouse sensitivity for rotation
     */
    public float $sensitivity = 0.15;

    /**
     * Movement speed (units per update tick)
     */
    public float $moveSpeed = 0.3;

    /**
     * Sprint speed multiplier (when holding shift)
     */
    public float $sprintMultiplier = 3.0;

    // -- Orbit mode settings --

    /**
     * Orbit target point in world space
     */
    public Vec3 $orbitTarget;

    /**
     * Current orbit distance from target
     */
    public float $orbitDistance = 10.0;

    /**
     * Minimum orbit distance
     */
    public float $orbitDistanceMin = 1.0;

    /**
     * Maximum orbit distance
     */
    public float $orbitDistanceMax = 100.0;

    /**
     * Scroll zoom speed for orbit mode
     */
    public float $orbitZoomSpeed = 1.0;

    /**
     * Orbit panning speed (middle-mouse drag)
     */
    public float $orbitPanSpeed = 0.01;

    // -- Third-person mode settings --

    /**
     * Target entity to follow in third-person mode (0 = none)
     */
    public int $followTarget = 0;

    /**
     * Vertical offset above the follow target
     */
    public float $followHeightOffset = 1.5;

    /**
     * Distance from the follow target
     */
    public float $followDistance = 5.0;

    /**
     * Minimum follow distance
     */
    public float $followDistanceMin = 1.5;

    /**
     * Maximum follow distance
     */
    public float $followDistanceMax = 20.0;

    /**
     * Smooth damping factor for follow camera (0 = instant, 1 = no movement)
     */
    public float $followDamping = 0.1;

    /**
     * Smooth scroll zoom speed for follow camera
     */
    public float $followZoomSpeed = 0.5;

    public function __construct()
    {
        $this->orbitTarget = new Vec3(0.0, 0.0, 0.0);
    }
}
