<?php

namespace VISU\Signals\UI;

use VISU\Signal\Signal;

class UIEventSignal extends Signal
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $event,
        public readonly array $data = [],
    ) {
    }
}
