<?php

namespace VISU\System;

use GL\Math\{GLM, Mat4, Quat, Vec2, Vec3};
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Exception\VISUException;
use VISU\Geo\Frustum;
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

class VISUCameraSystem implements SystemInterface
{
    /**
     * The currently active camera entity
     */
    public int $activeCameraEntity = 0;

    /**
     * Euler angles for default camera
     */
    private Vec2 $cameraEuler;

    /**
     * Camera modes
     */
    const CAMERA_MODE_GAME = 0;
    const CAMERA_MODE_FLYING = 1;

    /**
     * Current camera mode, this basically determines the subroutine that is used to update the camera
     */
    protected int $visuCameraMode = self::CAMERA_MODE_FLYING;

    /**
     * Cursor input queue
     * 
     * @var SignalQueue<CursorPosSignal>
     */
    private SignalQueue $inputCursorQueue;

    /**
     * Mouse Scroll input queue
     * 
     * @var SignalQueue<ScrollSignal>
     */
    private SignalQueue $inputScrollQueue;

    /**
     * Constructor
     */
    public function __construct(
        protected Input $input,
        protected Dispatcher $dispatcher,
    )
    {
        $this->cameraEuler = new Vec2();
    }

    /**
     * Sets the currently active camera entity
     */
    public function setActiveCameraEntity(int $entity) : void
    {
        $this->activeCameraEntity = $entity;
    }

    /**
     * Registers the system, this is where you should register all required components.
     * 
     * @return void 
     */
    public function register(EntitiesInterface $entities) : void
    {
        $entities->registerComponent(Camera::class);

        $this->inputCursorQueue = $this->dispatcher->createSignalQueue(Input::EVENT_CURSOR);
        $this->inputScrollQueue = $this->dispatcher->createSignalQueue(Input::EVENT_SCROLL);
    }

    /**
     * Unregisters the system, this is where you can handle any cleanup.
     * 
     * @return void 
     */
    public function unregister(EntitiesInterface $entities) : void
    {
        $this->dispatcher->destroySignalQueue($this->inputCursorQueue);
        $this->dispatcher->destroySignalQueue($this->inputScrollQueue);
    }

    /**
     * Cursor position handler
     * 
     * @param CursorPosSignal $signal
     */
    public function handleCursorPos(EntitiesInterface $entities, CursorPosSignal $signal) : void
    {
        if (!$this->input->isContextUnclaimed()) {
            return;
        }

        switch ($this->visuCameraMode) {
            case self::CAMERA_MODE_GAME:
                $this->handleCursorPosVISUGame($entities, $signal);
                break;
            case self::CAMERA_MODE_FLYING:
                $this->handleCursorPosVISUFlying($signal);
                break;
        }
    }

    /**
     * Scroll wheel handler
     * 
     * @param ScrollSignal $signal
     */
    public function handleScroll(EntitiesInterface $entities, ScrollSignal $signal) : void
    {
        if (!$this->input->isContextUnclaimed()) {
            return;
        }
        
        switch ($this->visuCameraMode) {
            case self::CAMERA_MODE_GAME:
                $this->handleScrollVISUGame($entities, $signal);
                break;
            case self::CAMERA_MODE_FLYING:
                $this->handleScrollVISUFlying($signal);
                break;
        }
    }

    /**
     * Override this method to handle the cursor position in game mode
     * 
     * @param CursorPosSignal $signal 
     * @return void 
     */
    protected function handleCursorPosVISUGame(EntitiesInterface $entities, CursorPosSignal $signal) : void
    {
        throw new VISUException('You need to override this method to handle the cursor position in game mode');
    }

    /**
     * Override this method to handle the scroll wheel in game mode
     * 
     * @param ScrollSignal $signal
     * @return void 
     */
    protected function handleScrollVISUGame(EntitiesInterface $entities, ScrollSignal $signal) : void
    {
        throw new VISUException('You need to override this method to handle the scroll wheel in game mode');
    }

    /**
     * Cursor position handler
     * 
     * @param CursorPosSignal $signal
     */
    private function handleCursorPosVISUFlying(CursorPosSignal $signal) : void
    {
        // to avoid a jump when the mouse button is pressed, we need to reset the last cursor position
        // initially when the click starts
        if ($this->input->hasMouseButtonBeenPressed(MouseButton::LEFT)) {
            return;
        }

        $cursorOffset = new Vec2($signal->offsetX, $signal->offsetY);
        
        if ($this->input->isMouseButtonPressed(MouseButton::LEFT)) {
            $this->cameraEuler->x = $this->cameraEuler->x - $cursorOffset->x * 0.1;
            $this->cameraEuler->y = $this->cameraEuler->y - $cursorOffset->y * 0.1;
        }
        else {
            $this->input->setCursorMode(CursorMode::NORMAL);
        }
    }

    /**
     * Scroll wheel handler
     * 
     * @param ScrollSignal $signal
     */
    private function handleScrollVISUFlying(ScrollSignal $signal) : void
    {
        // ... 
    }

    /**
     * Returns the active camera object or throws an exception if no camera is set,
     * or cannot be retrieved.
     * 
     * @return Camera
     */
    public function getActiveCamera(EntitiesInterface $entities) : Camera
    {
        if ($this->activeCameraEntity === 0 || !$entities->valid($this->activeCameraEntity)) {
            throw new VISUException('No active camera entity set, please set one using the `setActiveCameraEntity` method.');
        }

        // fetch the camera component from the active camera entity
        if (!$entities->has($this->activeCameraEntity, Camera::class)) {
            throw new VISUException('The active camera entity does not have a camera component.');
        }

        return $entities->get($this->activeCameraEntity, Camera::class);
    }

    /**
     * Updates handler, this is where the game state should be updated.
     * 
     * @return void 
     */
    public function update(EntitiesInterface $entities) : void
    {   
        $camera = $this->getActiveCamera($entities);
        // update interpolation states
        $camera->finalizeFrame();

        while($cursorSignal = $this->inputCursorQueue->shift()) {
            $this->handleCursorPos($entities, $cursorSignal);
        }

        while($scrollSignal = $this->inputScrollQueue->shift()) {
            $this->handleScroll($entities, $scrollSignal);
        }


        // if the context is claimed, we don't want to update the camera
        if (!$this->input->isContextUnclaimed()) {
            return;
        }

        // update the camera
        switch ($this->visuCameraMode) {
            case self::CAMERA_MODE_GAME:
                $this->updateGameCamera($entities, $camera);
                break;
            case self::CAMERA_MODE_FLYING:
                $this->updateVISUFlyingCamera($entities, $camera);
                break;
        }

    }

    /**
     * Override this method to update the camera in game mode
     * 
     * @param EntitiesInterface $entities
     */
    public function updateGameCamera(EntitiesInterface $entities, Camera $camera) : void
    {
        throw new VISUException('You need to override this method to update the camera in game mode');
    }

    /**
     * Flying camera mode update
     * 
     * @param EntitiesInterface $entities
     */
    public function updateVISUFlyingCamera(EntitiesInterface $entities, Camera $camera) : void
    {
        $input = $this->input;

        // to avoid a jump when the mouse button is pressed, we need to reset the last cursor position
        // initially when the click starts
        if ($this->input->hasMouseButtonBeenPressed(MouseButton::LEFT)) {
            $this->input->setCursorMode(CursorMode::DISABLED);
        }

        if ($input->isKeyPressed(Key::W)) {
            $camera->transform->moveForward(0.5);
        }
        if ($input->isKeyPressed(Key::S)) {
            $camera->transform->moveBackward(0.5);
        }
        if ($input->isKeyPressed(Key::A)) {
            $camera->transform->moveLeft(0.5);
        }
        if ($input->isKeyPressed(Key::D)) {
            $camera->transform->moveRight(0.5);
        }

        // update the camera rotation
        $quatX = new Quat;
        $quatX->rotate(GLM::radians($this->cameraEuler->x), new Vec3(0.0, 1.0, 0.0));
        
        $quatY = new Quat;
        $quatY->rotate(GLM::radians($this->cameraEuler->y), new Vec3(1.0, 0.0, 0.0));

        $camera->transform->setOrientation($quatX * $quatY);
        $camera->transform->markDirty();
    }

    private ?Mat4 $frozenView = null;

    /**
     * Create a camera data structure for the given render target.
     * 
     * @param EntitiesInterface $entities 
     * @param RenderTarget $renderTarget 
     * @param float $compensation 
     * 
     * @return CameraData 
     */
    public function getCameraData(EntitiesInterface $entities, RenderTarget $renderTarget, float $compensation) : CameraData
    {
        $camera = $this->getActiveCamera($entities);

        // extract the camera view and projection matrices
        $viewMatrix = $camera->getViewMatrix($compensation);
        $projectionMatrix = $camera->getProjectionMatrix($renderTarget);

        /** @var Mat4 */
        $projectionViewMatrix = $projectionMatrix * $viewMatrix;
        $inverseProjectionViewMatrix = Mat4::inverted($projectionViewMatrix);

        if ($this->input->isMouseButtonPressed(MouseButton::RIGHT)) {
            $this->frozenView = $viewMatrix->copy();
        } elseif ($this->input->isMouseButtonPressed(MouseButton::MIDDLE)) {
            $this->frozenView = null;
        }

        global $showFrustum;
        if ($this->frozenView) {
            // // debug
            // $testView = new Mat4;
            // $testView->rotate(GLM::radians(45.0), new Vec3(1.0, 0.0, 0.0));
            // $testView->rotate(GLM::radians(sin(glfwGetTime()) * 90), new Vec3(0.0, 1.0, 0.0));

            $fakeView = $this->frozenView->copy();
            $projectionViewMatrix = $projectionMatrix * $fakeView;
            $inverseProjectionViewMatrix = Mat4::inverted($projectionViewMatrix);

            $showFrustum = true;
        } else {
            $showFrustum = false;
        }

        return new CameraData(
            frameCamera: $camera,
            renderCamera: $camera,
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
            viewport: $camera->projectionMode !== CameraProjectionMode::perspective ? $camera->getViewport($renderTarget) : null,
        );
    }

    /**
     * Handles rendering of the scene, here you can attach additional render passes,
     * modify the render pipeline or customize rendering related data.
     * 
     * @param RenderContext $context
     */
    public function render(EntitiesInterface $entities, RenderContext $context) : void
    {
        // get current render target
        $renderTarget = $context->resources->getActiveRenderTarget();

        // store the camera data for the frame
        $context->data->set($this->getCameraData($entities, $renderTarget, $context->compensation));
    }
}
