<?php

namespace VISU\System;

use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\Pass\Camera2DData;
use VISU\Graphics\Rendering\RenderContext;

class Camera2DSystem implements SystemInterface
{
    /**
     * Camera 2D data, shared with SpriteBatchPass.
     */
    public Camera2DData $cameraData;

    /**
     * Entity ID that the camera follows (null = no follow).
     */
    private ?int $followTarget = null;

    /**
     * Smooth follow damping (0 = instant, 1 = no movement).
     */
    private float $followDamping = 0.1;

    /**
     * Zoom limits.
     */
    private float $minZoom = 0.1;
    private float $maxZoom = 10.0;

    /**
     * World bounds (null = no bounds).
     *
     * @var array{float, float, float, float}|null [minX, minY, maxX, maxY]
     */
    private ?array $bounds = null;

    /**
     * Shake intensity (decays over time).
     */
    private float $shakeIntensity = 0.0;

    /**
     * Shake duration remaining.
     */
    private float $shakeDuration = 0.0;

    /**
     * Maximum shake offset in pixels.
     */
    private float $shakeMaxOffset = 10.0;

    /**
     * Current shake offset applied to the camera.
     */
    private float $shakeOffsetX = 0.0;
    private float $shakeOffsetY = 0.0;

    public function __construct()
    {
        $this->cameraData = new Camera2DData();
    }

    public function register(EntitiesInterface $entities): void
    {
    }

    public function unregister(EntitiesInterface $entities): void
    {
    }

    /**
     * Set the entity for the camera to follow.
     */
    public function setFollowTarget(?int $entityId, float $damping = 0.1): void
    {
        $this->followTarget = $entityId;
        $this->followDamping = $damping;
    }

    /**
     * Set zoom level.
     */
    public function setZoom(float $zoom): void
    {
        $this->cameraData->zoom = max($this->minZoom, min($this->maxZoom, $zoom));
    }

    /**
     * Adjust zoom by delta.
     */
    public function zoom(float $delta): void
    {
        $this->setZoom($this->cameraData->zoom + $delta);
    }

    /**
     * Set zoom limits.
     */
    public function setZoomLimits(float $min, float $max): void
    {
        $this->minZoom = $min;
        $this->maxZoom = $max;
    }

    /**
     * Set world bounds to clamp camera position.
     */
    public function setBounds(float $minX, float $minY, float $maxX, float $maxY): void
    {
        $this->bounds = [$minX, $minY, $maxX, $maxY];
    }

    /**
     * Set camera position directly.
     */
    public function setPosition(float $x, float $y): void
    {
        $this->cameraData->x = $x;
        $this->cameraData->y = $y;
        $this->clampToBounds();
    }

    /**
     * Trigger a camera shake effect.
     *
     * @param float $intensity Shake strength (0.0 to 1.0)
     * @param float $duration Duration in seconds
     * @param float $maxOffset Maximum pixel offset
     */
    public function shake(float $intensity = 0.5, float $duration = 0.3, float $maxOffset = 10.0): void
    {
        $this->shakeIntensity = max(0.0, min(1.0, $intensity));
        $this->shakeDuration = $duration;
        $this->shakeMaxOffset = $maxOffset;
    }

    /**
     * Returns whether the camera is currently shaking.
     */
    public function isShaking(): bool
    {
        return $this->shakeDuration > 0.0;
    }

    /**
     * Get the current shake offset (useful for external rendering).
     *
     * @return array{float, float}
     */
    public function getShakeOffset(): array
    {
        return [$this->shakeOffsetX, $this->shakeOffsetY];
    }

    public function update(EntitiesInterface $entities): void
    {
        // Remove previous shake offset before computing new position
        $this->cameraData->x -= $this->shakeOffsetX;
        $this->cameraData->y -= $this->shakeOffsetY;
        $this->shakeOffsetX = 0.0;
        $this->shakeOffsetY = 0.0;

        if ($this->followTarget !== null) {
            $transform = $entities->tryGet($this->followTarget, Transform::class);
            if ($transform !== null) {
                $targetX = $transform->position->x;
                $targetY = $transform->position->y;

                // Smooth follow (lerp)
                $t = 1.0 - $this->followDamping;
                $this->cameraData->x += ($targetX - $this->cameraData->x) * $t;
                $this->cameraData->y += ($targetY - $this->cameraData->y) * $t;
            }
        }

        $this->clampToBounds();

        // Apply shake
        if ($this->shakeDuration > 0.0) {
            // Decay factor based on remaining duration
            $decay = $this->shakeDuration > 0.0 ? min(1.0, $this->shakeDuration) : 0.0;
            $offset = $this->shakeIntensity * $this->shakeMaxOffset * $decay;

            $this->shakeOffsetX = ((mt_rand() / mt_getrandmax()) * 2.0 - 1.0) * $offset;
            $this->shakeOffsetY = ((mt_rand() / mt_getrandmax()) * 2.0 - 1.0) * $offset;

            $this->cameraData->x += $this->shakeOffsetX;
            $this->cameraData->y += $this->shakeOffsetY;

            // Use a fixed delta for now (system doesn't receive deltaTime)
            $this->shakeDuration -= 1.0 / 60.0;
            if ($this->shakeDuration <= 0.0) {
                $this->shakeDuration = 0.0;
                $this->shakeIntensity = 0.0;
                $this->shakeOffsetX = 0.0;
                $this->shakeOffsetY = 0.0;
            }
        }
    }

    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
    }

    private function clampToBounds(): void
    {
        if ($this->bounds === null) {
            return;
        }

        $this->cameraData->x = max($this->bounds[0], min($this->bounds[2], $this->cameraData->x));
        $this->cameraData->y = max($this->bounds[1], min($this->bounds[3], $this->cameraData->y));
    }
}
