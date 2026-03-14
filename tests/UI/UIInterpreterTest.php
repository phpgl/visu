<?php

namespace VISU\Tests\UI;

use PHPUnit\Framework\TestCase;
use VISU\Signal\Dispatcher;
use VISU\Signals\UI\UIEventSignal;
use VISU\UI\UIDataContext;
use VISU\UI\UIInterpreter;
use VISU\UI\UINodeType;

class UIInterpreterTest extends TestCase
{
    public function testNodeTypeEnum(): void
    {
        $this->assertSame('panel', UINodeType::Panel->value);
        $this->assertSame('label', UINodeType::Label->value);
        $this->assertSame('button', UINodeType::Button->value);
        $this->assertSame('progressbar', UINodeType::ProgressBar->value);
        $this->assertSame('checkbox', UINodeType::Checkbox->value);
        $this->assertSame('select', UINodeType::Select->value);
        $this->assertSame('image', UINodeType::Image->value);
        $this->assertSame('space', UINodeType::Space->value);
    }

    public function testNodeTypeFromString(): void
    {
        $this->assertSame(UINodeType::Panel, UINodeType::from('panel'));
        $this->assertSame(UINodeType::Button, UINodeType::from('button'));
        $this->assertNull(UINodeType::tryFrom('unknown'));
    }

    public function testDataContextIntegration(): void
    {
        $dispatcher = new Dispatcher();
        $ctx = new UIDataContext();
        $ctx->set('economy.money', 5000);

        $interpreter = new UIInterpreter($dispatcher, $ctx);
        $this->assertSame($ctx, $interpreter->getDataContext());
    }

    public function testSetDataContext(): void
    {
        $dispatcher = new Dispatcher();
        $interpreter = new UIInterpreter($dispatcher);

        $ctx = new UIDataContext();
        $ctx->set('test', 42);
        $interpreter->setDataContext($ctx);

        $this->assertSame(42, $interpreter->getDataContext()->get('test'));
    }

    public function testUIEventSignal(): void
    {
        $signal = new UIEventSignal('ui.new_project', ['cost' => 100]);
        $this->assertSame('ui.new_project', $signal->event);
        $this->assertSame(['cost' => 100], $signal->data);
    }

    public function testUIEventSignalDispatching(): void
    {
        $dispatcher = new Dispatcher();
        $received = null;

        $dispatcher->register('ui.event', function (UIEventSignal $signal) use (&$received): void {
            $received = $signal;
        });

        $dispatcher->dispatch('ui.event', new UIEventSignal('test.click', ['id' => 'btn1']));

        $this->assertNotNull($received);
        $this->assertSame('test.click', $received->event);
        $this->assertSame(['id' => 'btn1'], $received->data);
    }

    public function testResetStates(): void
    {
        $dispatcher = new Dispatcher();
        $interpreter = new UIInterpreter($dispatcher);

        // Just verify it doesn't throw
        $interpreter->resetStates();
        $this->assertFalse($interpreter->getCheckboxState('nonexistent'));
        $this->assertNull($interpreter->getSelectState('nonexistent'));
    }

    public function testRenderFileNotFound(): void
    {
        $dispatcher = new Dispatcher();
        $interpreter = new UIInterpreter($dispatcher);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('UI layout file not found');
        $interpreter->renderFile('/nonexistent/path.json');
    }

    public function testParseUILayoutJson(): void
    {
        // Test that our JSON schema parses correctly
        $json = '{
            "type": "panel",
            "layout": "column",
            "padding": 10,
            "children": [
                { "type": "label", "text": "Money: {economy.money}", "fontSize": 16 },
                { "type": "progressbar", "value": "{player.oxygen}", "color": "#0088ff" },
                { "type": "button", "label": "New Project", "event": "ui.new_project" }
            ]
        }';

        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertSame('panel', $data['type']);
        $this->assertCount(3, $data['children']);
        $this->assertSame('label', $data['children'][0]['type']);
        $this->assertSame('progressbar', $data['children'][1]['type']);
        $this->assertSame('button', $data['children'][2]['type']);
    }
}
