<?php

namespace Tests\Dialogue;

use PHPUnit\Framework\TestCase;
use VISU\Dialogue\DialogueTree;

class DialogueTreeTest extends TestCase
{
    public function testFromArray(): void
    {
        $data = [
            'id' => 'test_dialogue',
            'start' => 'greeting',
            'nodes' => [
                [
                    'id' => 'greeting',
                    'speaker' => 'NPC',
                    'text' => 'Hello, traveler!',
                    'next' => 'question',
                ],
                [
                    'id' => 'question',
                    'speaker' => 'NPC',
                    'text' => 'What brings you here?',
                    'choices' => [
                        ['text' => 'Adventure!', 'next' => 'adventure'],
                        ['text' => 'Trade', 'next' => 'trade'],
                    ],
                ],
                [
                    'id' => 'adventure',
                    'speaker' => 'NPC',
                    'text' => 'A brave soul!',
                ],
                [
                    'id' => 'trade',
                    'speaker' => 'NPC',
                    'text' => 'Let me show you my wares.',
                    'actions' => [
                        ['type' => 'set', 'target' => 'shop_open', 'value' => true],
                    ],
                ],
            ],
        ];

        $tree = DialogueTree::fromArray($data);

        $this->assertEquals('test_dialogue', $tree->id);
        $this->assertEquals('greeting', $tree->startNodeId);
        $this->assertTrue($tree->hasNode('greeting'));
        $this->assertTrue($tree->hasNode('question'));
        $this->assertTrue($tree->hasNode('adventure'));
        $this->assertTrue($tree->hasNode('trade'));
        $this->assertFalse($tree->hasNode('nonexistent'));

        $greeting = $tree->getNode('greeting');
        $this->assertNotNull($greeting);
        $this->assertEquals('NPC', $greeting->speaker);
        $this->assertEquals('Hello, traveler!', $greeting->text);
        $this->assertEquals('question', $greeting->next);

        $question = $tree->getNode('question');
        $this->assertNotNull($question);
        $this->assertCount(2, $question->choices);
        $this->assertEquals('Adventure!', $question->choices[0]->text);
        $this->assertEquals('adventure', $question->choices[0]->next);

        $trade = $tree->getNode('trade');
        $this->assertNotNull($trade);
        $this->assertCount(1, $trade->actions);
        $this->assertEquals('set', $trade->actions[0]->type);
        $this->assertEquals('shop_open', $trade->actions[0]->target);
        $this->assertTrue($trade->actions[0]->value);
    }

    public function testGetNodes(): void
    {
        $tree = DialogueTree::fromArray([
            'id' => 'test',
            'start' => 'a',
            'nodes' => [
                ['id' => 'a', 'text' => 'A'],
                ['id' => 'b', 'text' => 'B'],
            ],
        ]);

        $this->assertCount(2, $tree->getNodes());
    }
}
