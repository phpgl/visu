<?php

namespace VISU\Tests\Transpiler;

use PHPUnit\Framework\TestCase;
use VISU\Component\SpriteRenderer;
use VISU\Component\BoxCollider2D;
use VISU\ECS\ComponentRegistry;
use VISU\Transpiler\SceneTranspiler;

class SceneTranspilerTest extends TestCase
{
    private ComponentRegistry $registry;
    private SceneTranspiler $transpiler;

    protected function setUp(): void
    {
        $this->registry = new ComponentRegistry();
        $this->registry->register('SpriteRenderer', SpriteRenderer::class);
        $this->registry->register('BoxCollider2D', BoxCollider2D::class);
        $this->transpiler = new SceneTranspiler($this->registry);
    }

    public function testTranspileEmptyScene(): void
    {
        $code = $this->transpiler->transpileArray(
            ['entities' => []],
            'EmptyScene',
        );

        $this->assertStringContainsString('class EmptyScene', $code);
        $this->assertStringContainsString('public static function load(EntitiesInterface $entities): array', $code);
        $this->assertStringContainsString('return $ids;', $code);
        $this->assertStringContainsString('AUTO-GENERATED', $code);
    }

    public function testTranspileSingleEntity(): void
    {
        $data = [
            'entities' => [[
                'name' => 'Player',
                'transform' => ['position' => [10, 20, 0]],
                'components' => [
                    ['type' => 'SpriteRenderer', 'sprite' => 'player.png', 'width' => 32],
                ],
            ]],
        ];

        $code = $this->transpiler->transpileArray($data, 'TestScene');

        $this->assertStringContainsString("new NameComponent('Player')", $code);
        $this->assertStringContainsString('new Vec3(10.0, 20.0, 0.0)', $code);
        $this->assertStringContainsString('new SpriteRenderer()', $code);
        $this->assertStringContainsString("->sprite = 'player.png'", $code);
        $this->assertStringContainsString('->width = 32', $code);
        $this->assertStringContainsString('use VISU\\Component\\SpriteRenderer;', $code);
        $this->assertStringContainsString('use VISU\\Component\\NameComponent;', $code);
    }

    public function testTranspileChildEntities(): void
    {
        $data = [
            'entities' => [[
                'name' => 'Parent',
                'transform' => ['position' => [0, 0, 0]],
                'children' => [[
                    'name' => 'Child',
                    'transform' => ['position' => [5, 5, 0]],
                ]],
            ]],
        ];

        $code = $this->transpiler->transpileArray($data, 'HierarchyScene');

        // Should have setParent call for child
        $this->assertStringContainsString('->setParent($entities, $e0)', $code);
        $this->assertStringContainsString("new NameComponent('Parent')", $code);
        $this->assertStringContainsString("new NameComponent('Child')", $code);
    }

    public function testTranspileOmitsDefaultValues(): void
    {
        $data = [
            'entities' => [[
                'transform' => ['position' => [0, 0, 0], 'scale' => [1, 1, 1]],
            ]],
        ];

        $code = $this->transpiler->transpileArray($data, 'DefaultScene');

        // Default position (0,0,0) and scale (1,1,1) should be omitted
        $this->assertStringNotContainsString('new Vec3(0.0, 0.0, 0.0)', $code);
        $this->assertStringNotContainsString('->scale', $code);
        $this->assertStringContainsString('markDirty()', $code);
    }

    public function testTranspileNonDefaultScale(): void
    {
        $data = [
            'entities' => [[
                'transform' => ['position' => [0, 0, 0], 'scale' => [2, 2, 1]],
            ]],
        ];

        $code = $this->transpiler->transpileArray($data, 'ScaledScene');
        $this->assertStringContainsString('new Vec3(2.0, 2.0, 1.0)', $code);
    }

    public function testTranspileRotation(): void
    {
        $data = [
            'entities' => [[
                'transform' => ['rotation' => [45, 0, 90]],
            ]],
        ];

        $code = $this->transpiler->transpileArray($data, 'RotatedScene');
        $this->assertStringContainsString('GLM::radians(45.0)', $code);
        $this->assertStringContainsString('GLM::radians(90.0)', $code);
        $this->assertStringContainsString('use GL\\Math\\GLM;', $code);
        $this->assertStringContainsString('use GL\\Math\\Quat;', $code);
    }

    public function testTranspileMultipleComponents(): void
    {
        $data = [
            'entities' => [[
                'transform' => [],
                'components' => [
                    ['type' => 'SpriteRenderer', 'sprite' => 'a.png'],
                    ['type' => 'BoxCollider2D', 'halfWidth' => 16.0, 'halfHeight' => 16.0],
                ],
            ]],
        ];

        $code = $this->transpiler->transpileArray($data, 'MultiCompScene');

        $this->assertStringContainsString('new SpriteRenderer()', $code);
        $this->assertStringContainsString('new BoxCollider2D()', $code);
        $this->assertStringContainsString('->halfWidth = 16.0', $code);
        $this->assertStringContainsString('use VISU\\Component\\BoxCollider2D;', $code);
    }

    public function testTranspileBooleanProperties(): void
    {
        $data = [
            'entities' => [[
                'transform' => [],
                'components' => [
                    ['type' => 'SpriteRenderer', 'flipX' => true, 'flipY' => false],
                ],
            ]],
        ];

        $code = $this->transpiler->transpileArray($data, 'BoolScene');
        $this->assertStringContainsString('->flipX = true', $code);
        $this->assertStringContainsString('->flipY = false', $code);
    }

    public function testTranspileArrayProperties(): void
    {
        $data = [
            'entities' => [[
                'transform' => [],
                'components' => [
                    ['type' => 'SpriteRenderer', 'color' => [1.0, 0.5, 0.0, 1.0]],
                ],
            ]],
        ];

        $code = $this->transpiler->transpileArray($data, 'ArrayScene');
        $this->assertStringContainsString('[1.0, 0.5, 0.0, 1.0]', $code);
    }

    public function testTranspileFromFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'scene_') . '.json';
        file_put_contents($tmpFile, json_encode([
            'entities' => [[
                'name' => 'FileTest',
                'transform' => ['position' => [1, 2, 3]],
            ]],
        ]));

        try {
            $code = $this->transpiler->transpile($tmpFile, 'FileScene');
            $this->assertStringContainsString("new NameComponent('FileTest')", $code);
            $this->assertStringContainsString("Source: {$tmpFile}", $code);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testTranspileCustomNamespace(): void
    {
        $code = $this->transpiler->transpileArray(
            ['entities' => []],
            'CustomScene',
            'App\\Scenes',
        );

        $this->assertStringContainsString('namespace App\\Scenes;', $code);
    }

    public function testGeneratedCodeIsSyntacticallyValid(): void
    {
        $data = [
            'entities' => [
                [
                    'name' => 'Root',
                    'transform' => ['position' => [10, 20, 0], 'scale' => [2, 2, 1]],
                    'components' => [
                        ['type' => 'SpriteRenderer', 'sprite' => 'test.png', 'width' => 64, 'height' => 64, 'flipX' => true],
                        ['type' => 'BoxCollider2D', 'halfWidth' => 32.0, 'halfHeight' => 32.0, 'isTrigger' => true],
                    ],
                    'children' => [
                        [
                            'name' => 'Child1',
                            'transform' => ['position' => [5, 5, 0]],
                            'components' => [['type' => 'SpriteRenderer', 'sprite' => 'child.png']],
                        ],
                    ],
                ],
            ],
        ];

        $code = $this->transpiler->transpileArray($data, 'SyntaxTestScene');

        // Verify the generated code can be parsed by PHP
        $result = exec('echo ' . escapeshellarg($code) . ' | php -l 2>&1', $output, $exitCode);
        $this->assertSame(0, $exitCode, "Generated code has syntax errors:\n" . implode("\n", $output));
    }
}
