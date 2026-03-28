<?php

namespace VISU\Dialogue;

class DialogueNode
{
    /**
     * @param string $id Unique node identifier
     * @param string $speaker Character name or empty for narration
     * @param string $text Dialogue text (supports {variable} interpolation)
     * @param array<DialogueChoice> $choices Player choices (empty = auto-advance)
     * @param ?string $next Next node ID for auto-advance (null = end of dialogue)
     * @param ?string $condition Optional condition expression to check if node is reachable
     * @param array<DialogueAction> $actions Actions to execute when node is entered
     */
    public function __construct(
        public readonly string $id,
        public readonly string $speaker = '',
        public readonly string $text = '',
        public readonly array $choices = [],
        public readonly ?string $next = null,
        public readonly ?string $condition = null,
        public readonly array $actions = [],
    ) {
    }
}
