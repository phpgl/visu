<?php 

namespace VISU\Signals\ECS;

use VISU\Signal\Signal;

class EntitySelectedSignal extends Signal
{
    /**
     * The entity that has been selected
     */
    public readonly int $entityId;

    /**
     * The source that triggered the selection
     */
    public readonly int $selectionSource;

    /**
     * Available selection sources
     */
    const SELECTION_SOURCE_DEV_PICKER = 1;

    /**
     * Constructor
     */
    public function __construct(
        int $entityId,
        int $selectionSource
    ) {
        $this->entityId = $entityId;
        $this->selectionSource = $selectionSource;
    }
}
