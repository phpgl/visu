<?php

namespace VISU\ECS\Picker;

use GL\Buffer\UByteBuffer;
use GL\Math\Vec4;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\EntityRegisty;
use VISU\ECS\Exception\EntityPickerException;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\RenderTarget;
use VISU\Signal\Dispatcher;
use VISU\Signals\ECS\EntitySelectedSignal;
use VISU\Signals\Input\MouseClickSignal;

/**
 * Why is the prefixed "dev"? Because the way this picker works is by 
 * rendering available entities to a backbuffer and then reading the
 * backbuffer to determine the entity ID. This is a very slow process
 * and should only be used for debugging purposes, editor and nothing in direct gameplay.
 * 
 * Why not use a raycast? Because that would require us to generate bounding boxes or some 
 * form of collision detection for all entities which is difficult to say the least.
 */
class DevEntityPicker
{
    /**
     * Array of systems that can produce pickable geometry
     * 
     * @var array<DevEntityPickerRenderInterface>
     */
    private array $systems;

    /**
     * If enabled the picker will run on mouse click events
     */
    public bool $enabled = true;

    /**
     * Function id for "handleMouseClick" handler
     */
    private int $handleMouseClickId;

    /**
     * Constructor
     * 
     * @param array<DevEntityPickerRenderInterface> $systems Array of systems that can produce pickable geometry
     */
    public function __construct(
        private DevEntityPickerDelegate $delegate,
        private EntityRegisty $entities,
        private Dispatcher $dispatcher,
        private RenderTarget $renderTarget,
        array $systems, // Array of systems that can produce pickable geometry
    )
    {
        // validate all systems are of the correct type
        foreach($systems as $system) {
            if (!$system instanceof DevEntityPickerRenderInterface) {
                throw new EntityPickerException("All system that contribute to entity picking must extend the 'DevEntityPickerRenderInterface'.");
            }
        }

        $this->systems = $systems;

        // register a click event handler
        $this->handleMouseClickId = $this->dispatcher->register('input.mouse_click', [$this, 'handleMouseClick']);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->dispatcher->unregister('input.mouse_click', $this->handleMouseClickId);
    }

    /**
     * Handles mouse click events
     */
    public function handleMouseClick(MouseClickSignal $signal) : void
    {
        if (!$this->enabled) {
            return;
        }

        $entityId = self::pickEntity(
            $this->entities, 
            $this->renderTarget,
            $this->delegate->devEntityPickerRequestsCameraData(), 
            $this->systems,
            (int) $signal->position->x, 
            (int) $signal->position->y
        );

        // inform the delegate
        $this->delegate->devEntityPickerDidSelectEntity($entityId);

        // inform the rest of the system
        $this->dispatcher->dispatch('ecs.entity_selected', new EntitySelectedSignal($entityId, EntitySelectedSignal::SELECTION_SOURCE_DEV_PICKER));
    }

    /**
     * Picks an entity visible on screen and returns its ID
     * 
     * @param EntitiesInterface $entities
     * @param CameraData $cameraData 
     * @param array<DevEntityPickerRenderInterface> $systems Array of systems that can produce pickable geometry
     * @param int $x In screen coordinates not pixels
     * @param int $y In screen coordinates not pixels
     * @return int 
     */
    public static function pickEntity(EntitiesInterface $entities, RenderTarget $renderTarget, CameraData $cameraData, array $systems, int $x, int $y) : int
    {
        $renderTarget->preparePass();
        $renderTarget->framebuffer()->clearColor = new Vec4(0, 0, 0, 0);
        $renderTarget->framebuffer()->clear();

        foreach($systems as $system) {
            if (!$system instanceof DevEntityPickerRenderInterface) {
                throw new EntityPickerException("All system that contribute to entity picking must extend the 'DevEntityPickerRenderInterface'.");
            }

            $system->renderEntityIdsForPicking($entities, $cameraData);
        }
        
        $buffer = new UByteBuffer();
        glReadPixels(
            (int) ($x * $renderTarget->contentScaleX), 
            (int) ($renderTarget->height() - ($y * $renderTarget->contentScaleY)), 
            1, 1, GL_RGB, GL_UNSIGNED_BYTE, $buffer
        );

        // Convert the RGB value to an entity ID
        $entityId = $buffer[0] + $buffer[1] * 256 + $buffer[2] * 256 * 256;

        return $entityId;
    }
}