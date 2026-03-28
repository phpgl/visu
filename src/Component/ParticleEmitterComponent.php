<?php

namespace VISU\Component;

use GL\Math\{Vec3, Vec4};
use VISU\Graphics\Texture;

class ParticleEmitterComponent
{
    /**
     * Emission shape
     */
    public ParticleEmitterShape $shape = ParticleEmitterShape::Point;

    /**
     * Particles emitted per second
     */
    public float $emissionRate = 10.0;

    /**
     * Instant burst count (emitted once when emitter starts or restarts)
     */
    public int $burstCount = 0;

    /**
     * Maximum alive particles for this emitter
     */
    public int $maxParticles = 500;

    /**
     * Whether the emitter loops after duration
     */
    public bool $looping = true;

    /**
     * Total emitter duration in seconds (only used if not looping)
     */
    public float $duration = 5.0;

    /**
     * Whether the emitter is currently active
     */
    public bool $playing = true;

    // --- Shape parameters ---

    /**
     * Sphere emission radius (for Sphere and Cone shapes)
     */
    public float $sphereRadius = 1.0;

    /**
     * Cone emission half-angle in degrees
     */
    public float $coneAngle = 45.0;

    /**
     * Box emission half-extents
     */
    public Vec3 $boxHalfExtents;

    // --- Particle initial properties ---

    /**
     * Minimum particle lifetime in seconds
     */
    public float $lifetimeMin = 1.0;

    /**
     * Maximum particle lifetime in seconds
     */
    public float $lifetimeMax = 3.0;

    /**
     * Minimum initial speed
     */
    public float $speedMin = 1.0;

    /**
     * Maximum initial speed
     */
    public float $speedMax = 5.0;

    /**
     * Primary emission direction (normalized)
     */
    public Vec3 $direction;

    /**
     * Direction randomness: 0 = exact direction, 1 = fully random sphere
     */
    public float $directionRandomness = 0.0;

    // --- Color over lifetime ---

    /**
     * Start color (RGBA)
     */
    public Vec4 $startColor;

    /**
     * End color (RGBA, interpolated over lifetime)
     */
    public Vec4 $endColor;

    // --- Size over lifetime ---

    /**
     * Start size
     */
    public float $startSize = 1.0;

    /**
     * End size
     */
    public float $endSize = 0.0;

    // --- Physics ---

    /**
     * Gravity multiplier (applied as downward acceleration)
     */
    public float $gravityModifier = 0.0;

    /**
     * Velocity drag (0 = no drag, higher = more damping)
     */
    public float $drag = 0.0;

    // --- Rendering ---

    /**
     * Use additive blending (good for fire, sparks)
     */
    public bool $additiveBlending = false;

    /**
     * Optional particle texture (null = solid color)
     */
    public ?Texture $texture = null;

    // --- Runtime state (managed by ParticleSystem) ---

    /**
     * Accumulated time for emission rate
     */
    public float $emissionAccumulator = 0.0;

    /**
     * Elapsed time since emitter started
     */
    public float $elapsedTime = 0.0;

    /**
     * Whether the initial burst has been emitted
     */
    public bool $burstEmitted = false;

    public function __construct()
    {
        $this->direction = new Vec3(0.0, 1.0, 0.0);
        $this->boxHalfExtents = new Vec3(1.0, 1.0, 1.0);
        $this->startColor = new Vec4(1.0, 1.0, 1.0, 1.0);
        $this->endColor = new Vec4(1.0, 1.0, 1.0, 0.0);
    }
}
