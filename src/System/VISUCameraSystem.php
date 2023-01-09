<?php

namespace VISU\System;

use GL\Math\{GLM, Quat, Vec2, Vec3};
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Exception\VISUException;
use VISU\Graphics\Camera;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\RenderContext;
use VISU\OS\CursorMode;
use VISU\OS\Input;
use VISU\OS\Key;
use VISU\OS\MouseButton;
use VISU\Signal\Dispatcher;
use VISU\Signals\Input\CursorPosSignal;

class VISUCameraSystem implements SystemInterface
{
    /**
     * The currently active camera entity
     */
    public int $activeCameraEntity = 0;

    /**
     * Function id of the event handler
     */
    private int $eventHandlerIdInputCursor = -1;

    /**
     * Euler angles for default camera
     */
    private Vec2 $cameraEuler;

    /**
     * Constructor
     */
    public function __construct(
        private Input $input,
        private Dispatcher $dispatcher,
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

        $lastCurserPos = $this->input->getLastCursorPosition();

        $this->eventHandlerIdInputCursor = $this->dispatcher->register('input.cursor', function(CursorPosSignal $signal) use($lastCurserPos) {

            $cursorOffset = new Vec2($signal->x - $lastCurserPos->x, $signal->y - $lastCurserPos->y);
            
            if ($this->input->isMouseButtonPressed(MouseButton::LEFT)) {
                $this->input->setCursorMode(CursorMode::DISABLED);
                $this->cameraEuler->x = $this->cameraEuler->x - $cursorOffset->x * 0.1;
                $this->cameraEuler->y = $this->cameraEuler->y - $cursorOffset->y * 0.1;
            }
            else {
                $this->input->setCursorMode(CursorMode::NORMAL);
            }
        });
    }

    /**
     * Unregisters the system, this is where you can handle any cleanup.
     * 
     * @return void 
     */
    public function unregister(EntitiesInterface $entities) : void
    {
        $this->dispatcher->unregister('input.cursor', $this->eventHandlerIdInputCursor);
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
        $input = $this->input;
        $camera = $this->getActiveCamera($entities);

        // update interpolation states
        $camera->finalizeFrame();

        if ($input->isKeyPressed(Key::W)) {
            $camera->transform->moveForward(2.5);
        }
        if ($input->isKeyPressed(Key::S)) {
            $camera->transform->moveBackward(2.5);
        }
        if ($input->isKeyPressed(Key::A)) {
            $camera->transform->moveLeft(2.5);
        }
        if ($input->isKeyPressed(Key::D)) {
            $camera->transform->moveRight(2.5);
        }

        // update the camera rotation
        $quatX = new Quat;
        $quatX->rotate(GLM::radians($this->cameraEuler->x), new Vec3(0.0, 1.0, 0.0));
        
        $quatY = new Quat;
        $quatY->rotate(GLM::radians($this->cameraEuler->y), new Vec3(1.0, 0.0, 0.0));

        $camera->transform->setOrientation($quatX * $quatY);
        $camera->transform->markDirty();
    }

    /**
     * Handles rendering of the scene, here you can attach additional render passes,
     * modify the render pipeline or customize rendering related data.
     * 
     * @param RenderContext $context
     */
    public function render(EntitiesInterface $entities, RenderContext $context) : void
    {
        $camera = $this->getActiveCamera($entities);
        // get current render target
        $renderTarget = $context->resources->getActiveRenderTarget();
        
        // extract the camera view and projection matrices
        $viewMatrix = $camera->getViewMatrix($context->compensation);
        $projectionMatrix = $camera->getProjectionMatrix($renderTarget);

        // store the camera data for the frame
        $context->data->set(new CameraData(
            frameCamera: $camera,
            renderCamera: $camera,
            projection: $projectionMatrix,
            view: $viewMatrix,
        ));
    }
}
