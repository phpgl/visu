<?php

namespace VISU\Tests\Benchmark;

use VISU\Dialogue\DialogueManager;
use VISU\Dialogue\DialogueTree;

class DialogueBench
{
    private DialogueManager $manager;

    /** @var array<string, mixed> */
    private array $treeData;

    public function setUp(): void
    {
        $this->manager = new DialogueManager();

        // build a dialogue tree with 50 nodes, branching paths
        $nodes = [];
        for ($i = 0; $i < 50; $i++) {
            $node = [
                'id' => "node_{$i}",
                'speaker' => 'NPC',
                'text' => "This is dialogue node {$i} with {player_name} interpolation.",
            ];

            if ($i < 49 && $i % 5 === 0) {
                // branching node with 3 choices
                $node['choices'] = [
                    ['text' => 'Option A', 'next' => 'node_' . ($i + 1)],
                    ['text' => 'Option B', 'next' => 'node_' . ($i + 2), 'condition' => 'has_key'],
                    ['text' => 'Option C', 'next' => 'node_' . ($i + 3), 'actions' => [['type' => 'set', 'target' => 'visited', 'value' => true]]],
                ];
            } elseif ($i < 49) {
                $node['next'] = 'node_' . ($i + 1);
            }

            if ($i % 10 === 0) {
                $node['actions'] = [['type' => 'add', 'target' => 'xp', 'value' => 10]];
            }

            $nodes[] = $node;
        }

        $this->treeData = [
            'id' => 'bench_dialogue',
            'start' => 'node_0',
            'nodes' => $nodes,
        ];

        $tree = DialogueTree::fromArray($this->treeData);
        $this->manager->registerTree($tree);
        $this->manager->setVariable('player_name', 'TestPlayer');
        $this->manager->setVariable('has_key', true);
        $this->manager->setVariable('xp', 0);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchParseDialogueTree50Nodes(): void
    {
        DialogueTree::fromArray($this->treeData);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchAdvanceThroughDialogue(): void
    {
        $this->manager->startDialogue('bench_dialogue');
        for ($i = 0; $i < 10; $i++) {
            $node = $this->manager->getActiveNode();
            if ($node === null) break;

            if (count($node->choices) > 0) {
                $this->manager->selectChoice(0);
            } else {
                $this->manager->advance();
            }
        }
        $this->manager->endDialogue();
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(10000)
     * @Iterations(5)
     */
    public function benchConditionEvaluation(): void
    {
        $this->manager->evaluateCondition('has_key');
        $this->manager->evaluateCondition('!missing_var');
        $this->manager->evaluateCondition('xp >= 10');
        $this->manager->evaluateCondition('player_name == TestPlayer');
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(10000)
     * @Iterations(5)
     */
    public function benchTextInterpolation(): void
    {
        $this->manager->interpolateText('Hello {player_name}, you have {xp} XP and {missing} items.');
    }
}
