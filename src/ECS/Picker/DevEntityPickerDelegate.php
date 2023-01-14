<?php

namespace VISU\ECS\Picker;

use VISU\Graphics\Rendering\Pass\CameraData;

interface DevEntityPickerDelegate
{
    /**
     * Called when the dev entity picker has selected an entity
     * 
     * @param int $entityId 
     * @return void 
     */
    public function devEntityPickerDidSelectEntity(int $entityId): void;

    /**
     * Called when the dev entity picker is about to initate a selection and requires 
     * the delegate to return the current camera data
     * 
     * @return CameraData
     */
    public function devEntityPickerRequestsCameraData(): CameraData;
}