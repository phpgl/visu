<?php

namespace VISU\Dialogue;

class DialogueAction
{
    /**
     * @param string $type Action type (e.g. "set", "signal", "add", "branch")
     * @param string $target Variable name or signal name
     * @param mixed $value Value to set/add, or signal payload
     */
    public function __construct(
        public readonly string $type,
        public readonly string $target,
        public readonly mixed $value = null,
    ) {
    }
}
