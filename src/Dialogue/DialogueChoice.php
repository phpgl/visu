<?php

namespace VISU\Dialogue;

class DialogueChoice
{
    /**
     * @param string $text Choice display text
     * @param string $next Node ID to jump to when chosen
     * @param ?string $condition Optional condition expression (hide choice if false)
     * @param array<DialogueAction> $actions Actions to execute when chosen
     */
    public function __construct(
        public readonly string $text,
        public readonly string $next,
        public readonly ?string $condition = null,
        public readonly array $actions = [],
    ) {
    }
}
