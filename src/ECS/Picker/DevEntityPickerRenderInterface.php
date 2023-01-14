<?php

namespace VISU\ECS\Picker;

use VISU\ECS\EntitiesInterface;
use VISU\Graphics\Rendering\Pass\CameraData;

interface DevEntityPickerRenderInterface
{
    public function renderEntityIdsForPicking(EntitiesInterface $entities, CameraData $cameraData) : void;
}