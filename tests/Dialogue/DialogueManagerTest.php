<?php

namespace Tests\Dialogue;

use PHPUnit\Framework\TestCase;
use VISU\Dialogue\DialogueManager;
use VISU\Dialogue\DialogueTree;

class DialogueManagerTest extends TestCase
{
    private function buildTree(): DialogueTree
    {
        return DialogueTree::fromArray([
            'id' => 'quest',
            'start' => 'start',
            'nodes' => [
                [
                    'id' => 'start',
                    'speaker' => 'Guard',
                    'text' => 'Halt! Who goes there?',
                    'choices' => [
                        ['text' => 'A friend.', 'next' => 'friendly'],
                        ['text' => 'None of your business.', 'next' => 'hostile'],
                        [
                            'text' => 'I have a pass.',
                            'next' => 'pass',
                            'condition' => 'has_pass',
                        ],
                    ],
                ],
                [
                    'id' => 'friendly',
                    'speaker' => 'Guard',
                    'text' => 'Welcome, {player_name}!',
                    'next' => 'end',
                    'actions' => [
                        ['type' => 'set', 'target' => 'guard_disposition', 'value' => 'friendly'],
                    ],
                ],
                [
                    'id' => 'hostile',
                    'speaker' => 'Guard',
                    'text' => 'Move along then.',
                    'actions' => [
                        ['type' => 'add', 'target' => 'reputation', 'value' => -5],
                    ],
                ],
                [
                    'id' => 'pass',
                    'speaker' => 'Guard',
                    'text' => 'Very well, proceed.',
                ],
                [
                    'id' => 'end',
                    'speaker' => 'Guard',
                    'text' => 'Safe travels!',
                ],
            ],
        ]);
    }

    public function testStartDialogue(): void
    {
        $mgr = new DialogueManager();
        $mgr->registerTree($this->buildTree());

        $node = $mgr->startDialogue('quest');

        $this->assertNotNull($node);
        $this->assertEquals('start', $node->id);
        $this->assertTrue($mgr->isActive());
    }

    public function testStartNonexistentDialogue(): void
    {
        $mgr = new DialogueManager();
        $this->assertNull($mgr->startDialogue('missing'));
        $this->assertFalse($mgr->isActive());
    }

    public function testAdvance(): void
    {
        $mgr = new DialogueManager();
        $mgr->registerTree($this->buildTree());

        $mgr->startDialogue('quest');
        $node = $mgr->selectChoice(0); // "A friend."

        $this->assertNotNull($node);
        $this->assertEquals('friendly', $node->id);
        $this->assertEquals('friendly', $mgr->getVariable('guard_disposition'));

        // advance to 'end'
        $end = $mgr->advance();
        $this->assertNotNull($end);
        $this->assertEquals('end', $end->id);

        // advance past end
        $null = $mgr->advance();
        $this->assertNull($null);
        $this->assertFalse($mgr->isActive());
    }

    public function testHostilePathAction(): void
    {
        $mgr = new DialogueManager();
        $mgr->registerTree($this->buildTree());
        $mgr->setVariable('reputation', 10);

        $mgr->startDialogue('quest');
        $mgr->selectChoice(1); // "None of your business."

        $this->assertEquals(5, $mgr->getVariable('reputation')); // 10 + (-5) = 5
    }

    public function testConditionalChoice(): void
    {
        $mgr = new DialogueManager();
        $mgr->registerTree($this->buildTree());

        $mgr->startDialogue('quest');

        // without has_pass, only 2 choices available
        $choices = $mgr->getAvailableChoices();
        $this->assertCount(2, $choices);

        // end and restart with pass
        $mgr->endDialogue();
        $mgr->setVariable('has_pass', true);
        $mgr->startDialogue('quest');

        $choices = $mgr->getAvailableChoices();
        $this->assertCount(3, $choices);
        $this->assertEquals('I have a pass.', $choices[2]->text);
    }

    public function testTextInterpolation(): void
    {
        $mgr = new DialogueManager();
        $mgr->setVariable('player_name', 'Aragorn');

        $result = $mgr->interpolateText('Welcome, {player_name}! You have {gold} gold.');
        $this->assertEquals('Welcome, Aragorn! You have {gold} gold.', $result);
    }

    public function testConditionEvaluation(): void
    {
        $mgr = new DialogueManager();

        // truthy
        $mgr->setVariable('flag', true);
        $this->assertTrue($mgr->evaluateCondition('flag'));

        // falsy
        $mgr->setVariable('empty_flag', false);
        $this->assertFalse($mgr->evaluateCondition('empty_flag'));

        // negation
        $this->assertFalse($mgr->evaluateCondition('!flag'));
        $this->assertTrue($mgr->evaluateCondition('!empty_flag'));

        // comparison
        $mgr->setVariable('level', 10);
        $this->assertTrue($mgr->evaluateCondition('level >= 5'));
        $this->assertFalse($mgr->evaluateCondition('level < 5'));
        $this->assertTrue($mgr->evaluateCondition('level == 10'));
        $this->assertTrue($mgr->evaluateCondition('level != 99'));
        $this->assertFalse($mgr->evaluateCondition('level > 10'));
        $this->assertTrue($mgr->evaluateCondition('level <= 10'));

        // string comparison
        $mgr->setVariable('class', 'warrior');
        $this->assertTrue($mgr->evaluateCondition('class == warrior'));
    }

    public function testInvalidChoiceIndex(): void
    {
        $mgr = new DialogueManager();
        $mgr->registerTree($this->buildTree());
        $mgr->startDialogue('quest');

        $this->assertNull($mgr->selectChoice(-1));
        $this->assertNull($mgr->selectChoice(99));
    }

    public function testEndDialogue(): void
    {
        $mgr = new DialogueManager();
        $mgr->registerTree($this->buildTree());
        $mgr->startDialogue('quest');

        $mgr->endDialogue();
        $this->assertFalse($mgr->isActive());
        $this->assertNull($mgr->getActiveNode());
    }

    public function testVariables(): void
    {
        $mgr = new DialogueManager();
        $mgr->setVariable('key', 'value');

        $this->assertEquals('value', $mgr->getVariable('key'));
        $this->assertNull($mgr->getVariable('missing'));
        $this->assertEquals('default', $mgr->getVariable('missing', 'default'));
        $this->assertArrayHasKey('key', $mgr->getVariables());
    }
}
