<?php

namespace VISU\Dialogue;

class DialogueManager
{
    /**
     * @var array<string, DialogueTree> Loaded dialogue trees by ID
     */
    private array $trees = [];

    /**
     * @var array<string, mixed> Variables for condition evaluation and text interpolation
     */
    private array $variables = [];

    private ?DialogueTree $activeTree = null;
    private ?DialogueNode $activeNode = null;

    public function registerTree(DialogueTree $tree): void
    {
        $this->trees[$tree->id] = $tree;
    }

    /**
     * Load and register a dialogue tree from a JSON file.
     */
    public function loadFromFile(string $path): DialogueTree
    {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Failed to read dialogue file: {$path}");
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid dialogue JSON in: {$path}");
        }

        $tree = DialogueTree::fromArray($data);
        $this->registerTree($tree);
        return $tree;
    }

    public function setVariable(string $key, mixed $value): void
    {
        $this->variables[$key] = $value;
    }

    public function getVariable(string $key, mixed $default = null): mixed
    {
        return $this->variables[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    public function startDialogue(string $treeId): ?DialogueNode
    {
        if (!isset($this->trees[$treeId])) {
            return null;
        }

        $this->activeTree = $this->trees[$treeId];
        $node = $this->activeTree->getNode($this->activeTree->startNodeId);

        if ($node === null) {
            $this->activeTree = null;
            return null;
        }

        $this->activeNode = $node;
        $this->executeActions($node->actions);

        return $node;
    }

    public function isActive(): bool
    {
        return $this->activeNode !== null;
    }

    public function getActiveNode(): ?DialogueNode
    {
        return $this->activeNode;
    }

    /**
     * Advance to the next node (for nodes without choices).
     */
    public function advance(): ?DialogueNode
    {
        if ($this->activeNode === null || $this->activeTree === null) {
            return null;
        }

        if ($this->activeNode->next === null) {
            $this->endDialogue();
            return null;
        }

        $nextNode = $this->activeTree->getNode($this->activeNode->next);
        if ($nextNode === null) {
            $this->endDialogue();
            return null;
        }

        $this->activeNode = $nextNode;
        $this->executeActions($nextNode->actions);
        return $nextNode;
    }

    /**
     * Select a choice by index.
     */
    public function selectChoice(int $index): ?DialogueNode
    {
        if ($this->activeNode === null || $this->activeTree === null) {
            return null;
        }

        $availableChoices = $this->getAvailableChoices();

        if ($index < 0 || $index >= count($availableChoices)) {
            return null;
        }

        $choice = $availableChoices[$index];
        $this->executeActions($choice->actions);

        assert($this->activeTree !== null);
        $nextNode = $this->activeTree->getNode($choice->next);
        if ($nextNode === null) {
            $this->endDialogue();
            return null;
        }

        $this->activeNode = $nextNode;
        $this->executeActions($nextNode->actions);
        return $nextNode;
    }

    /**
     * Get choices available for the current node (filtered by conditions).
     *
     * @return array<DialogueChoice>
     */
    public function getAvailableChoices(): array
    {
        if ($this->activeNode === null) {
            return [];
        }

        $available = [];
        foreach ($this->activeNode->choices as $choice) {
            if ($choice->condition !== null && !$this->evaluateCondition($choice->condition)) {
                continue;
            }
            $available[] = $choice;
        }

        return $available;
    }

    /**
     * Interpolate variables in dialogue text: {variable.name} -> value
     */
    public function interpolateText(string $text): string
    {
        return (string)preg_replace_callback('/\{([a-zA-Z0-9_.]+)\}/', function (array $matches): string {
            return (string)($this->variables[$matches[1]] ?? $matches[0]);
        }, $text);
    }

    public function endDialogue(): void
    {
        $this->activeTree = null;
        $this->activeNode = null;
    }

    /**
     * Evaluate a simple condition expression.
     * Supports: "variable", "variable == value", "variable != value",
     * "variable > value", "variable >= value", "variable < value", "variable <= value"
     */
    public function evaluateCondition(string $condition): bool
    {
        $condition = trim($condition);

        // comparison operators
        if (preg_match('/^([a-zA-Z0-9_.]+)\s*(==|!=|>=|<=|>|<)\s*(.+)$/', $condition, $matches)) {
            $varValue = $this->variables[$matches[1]] ?? null;
            $operator = $matches[2];
            $compareValue = $this->parseValue(trim($matches[3]));

            return match ($operator) {
                '==' => $varValue == $compareValue,
                '!=' => $varValue != $compareValue,
                '>' => $varValue > $compareValue,
                '>=' => $varValue >= $compareValue,
                '<' => $varValue < $compareValue,
                '<=' => $varValue <= $compareValue,
            };
        }

        // negation: !variable
        if (str_starts_with($condition, '!')) {
            $key = substr($condition, 1);
            return empty($this->variables[$key]);
        }

        // truthy check: variable
        return !empty($this->variables[$condition]);
    }

    private function parseValue(string $raw): mixed
    {
        if ($raw === 'true') return true;
        if ($raw === 'false') return false;
        if ($raw === 'null') return null;
        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float)$raw : (int)$raw;
        }
        // strip quotes
        if ((str_starts_with($raw, '"') && str_ends_with($raw, '"'))
            || (str_starts_with($raw, "'") && str_ends_with($raw, "'"))) {
            return substr($raw, 1, -1);
        }
        return $raw;
    }

    /**
     * Execute dialogue actions (set variables, emit signals, etc.)
     *
     * @param array<DialogueAction> $actions
     */
    private function executeActions(array $actions): void
    {
        foreach ($actions as $action) {
            match ($action->type) {
                'set' => $this->variables[$action->target] = $action->value,
                'add' => $this->variables[$action->target] = ($this->variables[$action->target] ?? 0) + $action->value,
                default => null,
            };
        }
    }
}
