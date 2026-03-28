<?php

namespace VISU\Tests\Transpiler;

use PHPUnit\Framework\TestCase;
use VISU\Transpiler\UITranspiler;

class UITranspilerTest extends TestCase
{
    private UITranspiler $transpiler;

    protected function setUp(): void
    {
        $this->transpiler = new UITranspiler();
    }

    public function testTranspileLabel(): void
    {
        $data = ['type' => 'label', 'text' => 'Hello World', 'fontSize' => 16, 'bold' => true];
        $code = $this->transpiler->transpileArray($data, 'LabelUI');

        $this->assertStringContainsString("FlyUI::text('Hello World')", $code);
        $this->assertStringContainsString('->fontSize(16.0)', $code);
        $this->assertStringContainsString('->bold()', $code);
        $this->assertStringContainsString('class LabelUI', $code);
    }

    public function testTranspileLabelWithBinding(): void
    {
        $data = ['type' => 'label', 'text' => 'Money: {economy.money}'];
        $code = $this->transpiler->transpileArray($data, 'BindingUI');

        $this->assertStringContainsString("\$ctx->get('economy.money', '')", $code);
        $this->assertStringContainsString("'Money: '", $code);
    }

    public function testTranspilePureBinding(): void
    {
        $data = ['type' => 'label', 'text' => '{player.name}'];
        $code = $this->transpiler->transpileArray($data, 'PureBindUI');

        $this->assertStringContainsString("\$ctx->get('player.name', '')", $code);
    }

    public function testTranspilePanel(): void
    {
        $data = [
            'type' => 'panel',
            'layout' => 'row',
            'padding' => 10,
            'spacing' => 5,
            'children' => [
                ['type' => 'label', 'text' => 'A'],
                ['type' => 'label', 'text' => 'B'],
            ],
        ];

        $code = $this->transpiler->transpileArray($data, 'PanelUI');

        $this->assertStringContainsString('FlyUI::beginLayout(', $code);
        $this->assertStringContainsString('FUILayoutFlow::horizontal', $code);
        $this->assertStringContainsString('->spacing(5.0)', $code);
        $this->assertStringContainsString('Vec4(10.0, 10.0, 10.0, 10.0)', $code);
        $this->assertStringContainsString('FlyUI::end()', $code);
        $this->assertStringContainsString("FlyUI::text('A')", $code);
        $this->assertStringContainsString("FlyUI::text('B')", $code);
    }

    public function testTranspileButton(): void
    {
        $data = ['type' => 'button', 'label' => 'Click Me', 'event' => 'ui.click'];
        $code = $this->transpiler->transpileArray($data, 'ButtonUI');

        $this->assertStringContainsString("FlyUI::button('Click Me'", $code);
        $this->assertStringContainsString("new UIEventSignal('ui.click'", $code);
        $this->assertStringContainsString('use VISU\\Signals\\UI\\UIEventSignal;', $code);
    }

    public function testTranspileProgressBar(): void
    {
        $data = ['type' => 'progressbar', 'value' => '{health}', 'color' => '#ff0000', 'height' => 12];
        $code = $this->transpiler->transpileArray($data, 'BarUI');

        $this->assertStringContainsString("\$ctx->get('health', 0)", $code);
        $this->assertStringContainsString('VGColor::rgb(', $code);
        $this->assertStringContainsString('->height(12.0)', $code);
    }

    public function testTranspileCheckbox(): void
    {
        $data = ['type' => 'checkbox', 'text' => 'Enable', 'id' => 'cb_enable', 'event' => 'ui.toggle'];
        $code = $this->transpiler->transpileArray($data, 'CheckUI');

        $this->assertStringContainsString('static $cbState_cb_enable = false', $code);
        $this->assertStringContainsString("FlyUI::checkbox('Enable'", $code);
        $this->assertStringContainsString("new UIEventSignal('ui.toggle'", $code);
    }

    public function testTranspileSelect(): void
    {
        $data = ['type' => 'select', 'name' => 'priority', 'options' => ['High', 'Low'], 'event' => 'ui.sel'];
        $code = $this->transpiler->transpileArray($data, 'SelectUI');

        $this->assertStringContainsString("FlyUI::select('priority'", $code);
        $this->assertStringContainsString("'High', 'Low'", $code);
    }

    public function testTranspileSpace(): void
    {
        $data = ['type' => 'space', 'height' => 10];
        $code = $this->transpiler->transpileArray($data, 'SpaceUI');

        $this->assertStringContainsString('FlyUI::spaceY(10.0)', $code);
    }

    public function testTranspileImage(): void
    {
        $data = ['type' => 'image', 'width' => 64, 'height' => 64, 'color' => '#ff0000'];
        $code = $this->transpiler->transpileArray($data, 'ImageUI');

        $this->assertStringContainsString('fixedWidth(64.0)', $code);
        $this->assertStringContainsString('fixedHeight(64.0)', $code);
    }

    public function testTranspileFromFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ui_') . '.json';
        file_put_contents($tmpFile, json_encode([
            'type' => 'label',
            'text' => 'File Test',
        ]));

        try {
            $code = $this->transpiler->transpile($tmpFile, 'FileUI');
            $this->assertStringContainsString("FlyUI::text('File Test')", $code);
            $this->assertStringContainsString("Source: {$tmpFile}", $code);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testTranspileSizingModes(): void
    {
        $data = [
            'type' => 'panel',
            'horizontalSizing' => 'fit',
            'verticalSizing' => 'fill',
            'children' => [],
        ];

        $code = $this->transpiler->transpileArray($data, 'SizingUI');
        $this->assertStringContainsString('horizontalFit()', $code);
        $this->assertStringContainsString('verticalFill()', $code);
    }

    public function testTranspileBackgroundColor(): void
    {
        $data = [
            'type' => 'panel',
            'backgroundColor' => '#1a2b3c',
            'children' => [],
        ];

        $code = $this->transpiler->transpileArray($data, 'BgUI');
        $this->assertStringContainsString('backgroundColor(VGColor::rgb(', $code);
    }

    public function testGeneratedCodeIsSyntacticallyValid(): void
    {
        $data = [
            'type' => 'panel',
            'layout' => 'column',
            'padding' => 15,
            'spacing' => 8,
            'children' => [
                ['type' => 'label', 'text' => 'Title: {name}', 'fontSize' => 20, 'bold' => true, 'color' => '#ffffff'],
                ['type' => 'progressbar', 'value' => '{health}', 'color' => '#00ff00', 'height' => 12],
                ['type' => 'space', 'height' => 5],
                ['type' => 'button', 'label' => 'Action', 'event' => 'ui.action'],
                ['type' => 'checkbox', 'text' => 'Toggle', 'id' => 'cb1', 'event' => 'ui.toggle'],
            ],
        ];

        $code = $this->transpiler->transpileArray($data, 'FullUI');

        $result = exec('echo ' . escapeshellarg($code) . ' | php -l 2>&1', $output, $exitCode);
        $this->assertSame(0, $exitCode, "Generated UI code has syntax errors:\n" . implode("\n", $output));
    }
}
