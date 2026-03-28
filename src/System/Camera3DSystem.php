<?php

namespace VISU\System;

use GL\Math\{GLM, Quat, Vec2, Vec3};
use VISU\Component\Camera3DComponent;
use VISU\Component\Camera3DMode;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\Transform;
use VISU\Graphics\Camera;
use VISU\Graphics\CameraProjectionMode;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\RenderTarget;
use VISU\OS\CursorMode;
use VISU\OS\Input;
use VISU\OS\Key;
use VISU\OS\MouseButton;
use VISU\Signal\Dispatcher;
use VISU\Signal\SignalQueue;
use VISU\Signals\Input\CursorPosSignal;
use VISU\Signals\Input\ScrollSignal;

class Camera3DSystem implements SystemInterface
{
    private int $cameraEntity = 0;
    private Camera3DMode $mode = Camera3DMode::orbit;

    /**
     * @var SignalQueue<CursorPosSignal>
     */
    private SignalQueue $cursorQueue;

    /**
     * @var SignalQueue<ScrollSignal>
     */
    private SignalQueue $scrollQueue;

    /**
     * Accumulated scroll delta (consumed during update)
     */
    private float $scrollDelta = 0.0;

    /**
     * Accumulated cursor movement (consumed during update)
     */
    private Vec2 $cursorDelta;

    private Vec2 $panDelta;

    public function __construct(
        private Input $input,
        private Dispatcher $dispatcher,
    ) {
        $this->cursorDelta = new Vec2(0.0, 0.0);
        $this->panDelta = new Vec2(0.0, 0.0);
    }

    /**
     * Spawns a camera entity with Camera + Camera3DComponent and sets it active.
     */
    public function spawnCamera(
        EntitiesInterface $entities,
        Camera3DMode $mode,
        ?Vec3 $position = null,
    ): int {
        $entity = $entities->create();
        $camera = $entities->attach($entity, new Camera(CameraProjectionMode::perspective));
        $camera->nearPlane = 0.1;
        $camera->farPlane = 500.0;

        $comp = $entities->attach($entity, new Camera3DComponent());

        if ($position !== null) {
            $camera->transform->setPosition($position);
        }

        $this->cameraEntity = $entity;
        $this->mode = $mode;

        // initialize yaw/pitch from position for orbit mode
        if ($mode === Camera3DMode::orbit && $position !== null) {
            $dir = $position - $comp->orbitTarget;
            $comp->orbitDistance = $dir->length();
            if ($comp->orbitDistance > 0.001) {
                $normalized = Vec3::normalized($dir);
                $comp->yaw = atan2($normalized->x, $normalized->z) * (180.0 / M_PI);
                $comp->pitch = asin(max(-1.0, min(1.0, $normalized->y))) * (180.0 / M_PI);
            }
        }

        return $entity;
    }

    /**
     * Returns the active camera entity
     */
    public function getCameraEntity(): int
    {
        return $this->cameraEntity;
    }

    /**
     * Sets the camera mode at runtime
     */
    public function setMode(Camera3DMode $mode): void
    {
        $this->mode = $mode;
    }

    public function getMode(): Camera3DMode
    {
        return $this->mode;
    }

    public function register(EntitiesInterface $entities): void
    {
        $entities->registerComponent(Camera::class);
        $entities->registerComponent(Camera3DComponent::class);

        $this->cursorQueue = $this->dispatcher->createSignalQueue(Input::EVENT_CURSOR);
        $this->scrollQueue = $this->dispatcher->createSignalQueue(Input::EVENT_SCROLL);
    }

    public function unregister(EntitiesInterface $entities): void
    {
        $this->dispatcher->destroySignalQueue($this->cursorQueue);
        $this->dispatcher->destroySignalQueue($this->scrollQueue);
    }

    public function update(EntitiesInterface $entities): void
    {
        if ($this->cameraEntity === 0 || !$entities->valid($this->cameraEntity)) {
            return;
        }

        $camera = $entities->get($this->cameraEntity, Camera::class);
        $comp = $entities->get($this->cameraEntity, Camera3DComponent::class);

        $camera->finalizeFrame();

        // drain input queues
        $this->cursorDelta->x = 0.0;
        $this->cursorDelta->y = 0.0;
        $this->panDelta->x = 0.0;
        $this->panDelta->y = 0.0;

        while ($signal = $this->cursorQueue->shift()) {
            if (!$this->input->isContextUnclaimed()) continue;

            if ($this->mode === Camera3DMode::firstPerson) {
                // first-person always captures cursor movement when left mouse is held
                if ($this->input->isMouseButtonPressed(MouseButton::LEFT)
                    && !$this->input->hasMouseButtonBeenPressed(MouseButton::LEFT)) {
                    $this->cursorDelta->x += $signal->offsetX;
                    $this->cursorDelta->y += $signal->offsetY;
                }
            } else {
                // orbit and third-person: left-drag rotates
                if ($this->input->isMouseButtonPressed(MouseButton::LEFT)
                    && !$this->input->hasMouseButtonBeenPressed(MouseButton::LEFT)) {
                    $this->cursorDelta->x += $signal->offsetX;
                    $this->cursorDelta->y += $signal->offsetY;
                }
                // orbit: right-drag pans
                if ($this->input->isMouseButtonPressed(MouseButton::RIGHT)
                    && !$this->input->hasMouseButtonBeenPressed(MouseButton::RIGHT)) {
                    $this->panDelta->x += $signal->offsetX;
                    $this->panDelta->y += $signal->offsetY;
                }
            }
        }

        while ($signal = $this->scrollQueue->shift()) {
            if (!$this->input->isContextUnclaimed()) continue;
            $this->scrollDelta += $signal->y;
        }

        if (!$this->input->isContextUnclaimed()) {
            return;
        }

        // cursor mode management for first-person
        if ($this->mode === Camera3DMode::firstPerson) {
            if ($this->input->hasMouseButtonBeenPressed(MouseButton::LEFT)) {
                $this->input->setCursorMode(CursorMode::DISABLED);
            }
            if (!$this->input->isMouseButtonPressed(MouseButton::LEFT)) {
                $this->input->setCursorMode(CursorMode::NORMAL);
            }
        } else {
            // orbit/third-person: set disabled while left-dragging for smooth movement
            if ($this->input->hasMouseButtonBeenPressed(MouseButton::LEFT)) {
                $this->input->setCursorMode(CursorMode::DISABLED);
            }
            if (!$this->input->isMouseButtonPressed(MouseButton::LEFT)
                && !$this->input->isMouseButtonPressed(MouseButton::RIGHT)) {
                $this->input->setCursorMode(CursorMode::NORMAL);
            }
        }

        match ($this->mode) {
            Camera3DMode::orbit => $this->updateOrbit($entities, $camera, $comp),
            Camera3DMode::firstPerson => $this->updateFirstPerson($camera, $comp),
            Camera3DMode::thirdPerson => $this->updateThirdPerson($entities, $camera, $comp),
        };

        $this->scrollDelta = 0.0;
    }

    private function updateOrbit(EntitiesInterface $entities, Camera $camera, Camera3DComponent $comp): void
    {
        // rotation from cursor drag
        $comp->yaw -= $this->cursorDelta->x * $comp->sensitivity;
        $comp->pitch -= $this->cursorDelta->y * $comp->sensitivity;
        $comp->pitch = max($comp->pitchMin, min($comp->pitchMax, $comp->pitch));

        // zoom from scroll
        $comp->orbitDistance -= $this->scrollDelta * $comp->orbitZoomSpeed;
        $comp->orbitDistance = max($comp->orbitDistanceMin, min($comp->orbitDistanceMax, $comp->orbitDistance));

        // pan from right-drag
        if ($this->panDelta->x != 0.0 || $this->panDelta->y != 0.0) {
            $right = $camera->transform->dirRight();
            $up = $camera->transform->dirUp();
            $panScale = $comp->orbitPanSpeed * $comp->orbitDistance;
            /** @var Vec3 $newTarget */
            $newTarget = $comp->orbitTarget
                - $right * ($this->panDelta->x * $panScale)
                + $up * ($this->panDelta->y * $panScale);
            $comp->orbitTarget = $newTarget;
        }

        // compute camera position from spherical coordinates
        $yawRad = GLM::radians($comp->yaw);
        $pitchRad = GLM::radians($comp->pitch);
        $cosPitch = cos($pitchRad);

        $offset = new Vec3(
            sin($yawRad) * $cosPitch * $comp->orbitDistance,
            sin($pitchRad) * $comp->orbitDistance,
            cos($yawRad) * $cosPitch * $comp->orbitDistance,
        );

        $camera->transform->setPosition($comp->orbitTarget + $offset);
        $camera->transform->lookAt($comp->orbitTarget);
    }

    private function updateFirstPerson(Camera $camera, Camera3DComponent $comp): void
    {
        // rotation from cursor
        $comp->yaw -= $this->cursorDelta->x * $comp->sensitivity;
        $comp->pitch -= $this->cursorDelta->y * $comp->sensitivity;
        $comp->pitch = max($comp->pitchMin, min($comp->pitchMax, $comp->pitch));

        // apply orientation
        $quatYaw = new Quat();
        $quatYaw->rotate(GLM::radians($comp->yaw), new Vec3(0.0, 1.0, 0.0));
        $quatPitch = new Quat();
        $quatPitch->rotate(GLM::radians($comp->pitch), new Vec3(1.0, 0.0, 0.0));
        $camera->transform->setOrientation($quatYaw * $quatPitch);

        // movement
        $speed = $comp->moveSpeed;
        if ($this->input->isKeyPressed(Key::LEFT_SHIFT)) {
            $speed *= $comp->sprintMultiplier;
        }

        if ($this->input->isKeyPressed(Key::W)) {
            $camera->transform->moveForward($speed);
        }
        if ($this->input->isKeyPressed(Key::S)) {
            $camera->transform->moveBackward($speed);
        }
        if ($this->input->isKeyPressed(Key::A)) {
            $camera->transform->moveLeft($speed);
        }
        if ($this->input->isKeyPressed(Key::D)) {
            $camera->transform->moveRight($speed);
        }
        if ($this->input->isKeyPressed(Key::SPACE)) {
            $camera->transform->position->y += $speed;
            $camera->transform->markDirty();
        }
        if ($this->input->isKeyPressed(Key::LEFT_CONTROL)) {
            $camera->transform->position->y -= $speed;
            $camera->transform->markDirty();
        }
    }

    private function updateThirdPerson(EntitiesInterface $entities, Camera $camera, Camera3DComponent $comp): void
    {
        if ($comp->followTarget === 0 || !$entities->valid($comp->followTarget)) {
            return;
        }

        $targetTransform = $entities->get($comp->followTarget, Transform::class);
        $targetPos = $targetTransform->getWorldPosition($entities);
        $lookTarget = new Vec3($targetPos->x, $targetPos->y + $comp->followHeightOffset, $targetPos->z);

        // rotation from cursor drag
        $comp->yaw -= $this->cursorDelta->x * $comp->sensitivity;
        $comp->pitch -= $this->cursorDelta->y * $comp->sensitivity;
        $comp->pitch = max($comp->pitchMin, min($comp->pitchMax, $comp->pitch));

        // zoom from scroll
        $comp->followDistance -= $this->scrollDelta * $comp->followZoomSpeed;
        $comp->followDistance = max($comp->followDistanceMin, min($comp->followDistanceMax, $comp->followDistance));

        // compute desired position from spherical coordinates around target
        $yawRad = GLM::radians($comp->yaw);
        $pitchRad = GLM::radians($comp->pitch);
        $cosPitch = cos($pitchRad);

        $offset = new Vec3(
            sin($yawRad) * $cosPitch * $comp->followDistance,
            sin($pitchRad) * $comp->followDistance,
            cos($yawRad) * $cosPitch * $comp->followDistance,
        );

        $desiredPos = $lookTarget + $offset;

        // smooth follow with damping
        $currentPos = $camera->transform->position;
        $damping = $comp->followDamping;
        $newPos = new Vec3(
            $currentPos->x + ($desiredPos->x - $currentPos->x) * (1.0 - $damping),
            $currentPos->y + ($desiredPos->y - $currentPos->y) * (1.0 - $damping),
            $currentPos->z + ($desiredPos->z - $currentPos->z) * (1.0 - $damping),
        );

        $camera->transform->setPosition($newPos);
        $camera->transform->lookAt($lookTarget);
    }

    /**
     * Render pass: writes CameraData to the pipeline container
     */
    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
        if ($this->cameraEntity === 0) return;

        $camera = $entities->get($this->cameraEntity, Camera::class);
        $renderTarget = $context->resources->getActiveRenderTarget();
        $context->data->set($camera->createCameraData($renderTarget, $context->compensation));
    }
}
