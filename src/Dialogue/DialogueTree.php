<?php

namespace VISU\Dialogue;

class DialogueTree
{
    /**
     * @var array<string, DialogueNode>
     */
    private array $nodes = [];

    public function __construct(
        public readonly string $id,
        public readonly string $startNodeId,
    ) {
    }

    public function addNode(DialogueNode $node): void
    {
        $this->nodes[$node->id] = $node;
    }

    public function getNode(string $id): ?DialogueNode
    {
        return $this->nodes[$id] ?? null;
    }

    public function hasNode(string $id): bool
    {
        return isset($this->nodes[$id]);
    }

    /**
     * @return array<string, DialogueNode>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * Parse a dialogue tree from a JSON array structure.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $treeId = (string)($data['id'] ?? 'unnamed');
        $startNode = (string)($data['start'] ?? '');

        $tree = new self($treeId, $startNode);

        /** @var array<array<string, mixed>> $nodes */
        $nodes = $data['nodes'] ?? [];
        foreach ($nodes as $nodeData) {
            $tree->addNode(self::parseNode($nodeData));
        }

        return $tree;
    }

    /**
     * @param array<string, mixed> $nodeData
     */
    private static function parseNode(array $nodeData): DialogueNode
    {
        $choices = [];
        /** @var array<array<string, mixed>> $choicesData */
        $choicesData = $nodeData['choices'] ?? [];
        foreach ($choicesData as $choiceData) {
            $choiceActions = [];
            /** @var array<array<string, mixed>> $choiceActionsData */
            $choiceActionsData = $choiceData['actions'] ?? [];
            foreach ($choiceActionsData as $actionData) {
                $choiceActions[] = new DialogueAction(
                    (string)($actionData['type'] ?? ''),
                    (string)($actionData['target'] ?? ''),
                    $actionData['value'] ?? null,
                );
            }

            $choices[] = new DialogueChoice(
                (string)($choiceData['text'] ?? ''),
                (string)($choiceData['next'] ?? ''),
                isset($choiceData['condition']) ? (string)$choiceData['condition'] : null,
                $choiceActions,
            );
        }

        $actions = [];
        /** @var array<array<string, mixed>> $actionsData */
        $actionsData = $nodeData['actions'] ?? [];
        foreach ($actionsData as $actionData) {
            $actions[] = new DialogueAction(
                (string)($actionData['type'] ?? ''),
                (string)($actionData['target'] ?? ''),
                $actionData['value'] ?? null,
            );
        }

        return new DialogueNode(
            id: (string)($nodeData['id'] ?? ''),
            speaker: (string)($nodeData['speaker'] ?? ''),
            text: (string)($nodeData['text'] ?? ''),
            choices: $choices,
            next: isset($nodeData['next']) ? (string)$nodeData['next'] : null,
            condition: isset($nodeData['condition']) ? (string)$nodeData['condition'] : null,
            actions: $actions,
        );
    }
}
