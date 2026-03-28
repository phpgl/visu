<?php

namespace VISU\Tests\Transpiler;

use PHPUnit\Framework\TestCase;
use VISU\Component\SpriteRenderer;
use VISU\Component\SpriteAnimator;
use VISU\ECS\ComponentRegistry;
use VISU\Transpiler\PrefabTranspiler;

class PrefabTranspilerTest extends TestCase
{
    private PrefabTranspiler $transpiler;

    protected function setUp(): void
    {
        $registry = new ComponentRegistry();
        $registry->register('SpriteRenderer', SpriteRenderer::class);
        $registry->register('SpriteAnimator', SpriteAnimator::class);
        $this->transpiler = new PrefabTranspiler($registry);
    }

    public function testTranspilePrefab(): void
    {
        $data = [
            'name' => 'Enemy',
            'transform' => ['position' => [0, 0, 0]],
            'components' => [
                ['type' => 'SpriteRenderer', 'sprite' => 'enemy.png', 'width' => 32, 'height' => 32],
            ],
        ];

        $code = $this->transpiler->transpileArray($data, 'Enemy', 'VISU\\Generated\\Prefabs');

        $this->assertStringContainsString('class Enemy', $code);
        $this->assertStringContainsString('namespace VISU\\Generated\\Prefabs;', $code);
        $this->assertStringContainsString("new NameComponent('Enemy')", $code);
        $this->assertStringContainsString('new SpriteRenderer()', $code);
        $this->assertStringContainsString("->sprite = 'enemy.png'", $code);
    }

    public function testTranspilePrefabFromFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'prefab_') . '.json';
        file_put_contents($tmpFile, json_encode([
            'name' => 'Bullet',
            'transform' => ['position' => [0, 0, 0]],
            'components' => [
                ['type' => 'SpriteRenderer', 'sprite' => 'bullet.png'],
            ],
        ]));

        try {
            $code = $this->transpiler->transpile($tmpFile, 'Bullet');
            $this->assertStringContainsString("new NameComponent('Bullet')", $code);
            $this->assertStringContainsString("Source: {$tmpFile}", $code);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testTranspilePrefabWithAnimator(): void
    {
        $data = [
            'name' => 'NPC',
            'transform' => [],
            'components' => [
                ['type' => 'SpriteRenderer', 'sprite' => 'npc.png'],
                [
                    'type' => 'SpriteAnimator',
                    'currentAnimation' => 'idle',
                    'animations' => [
                        'idle' => ['frames' => [[0, 0, 0.25, 1]], 'fps' => 4, 'loop' => true],
                    ],
                ],
            ],
        ];

        $code = $this->transpiler->transpileArray($data, 'NPC');

        $this->assertStringContainsString('new SpriteAnimator()', $code);
        $this->assertStringContainsString("->currentAnimation = 'idle'", $code);
        $this->assertStringContainsString('use VISU\\Component\\SpriteAnimator;', $code);
    }

    public function testGeneratedCodeIsSyntacticallyValid(): void
    {
        $data = [
            'name' => 'ValidPrefab',
            'transform' => ['position' => [10, 20, 0], 'scale' => [2, 2, 1]],
            'components' => [
                ['type' => 'SpriteRenderer', 'sprite' => 'test.png', 'width' => 16, 'height' => 24, 'flipX' => true],
            ],
        ];

        $code = $this->transpiler->transpileArray($data, 'ValidPrefab');

        $result = exec('echo ' . escapeshellarg($code) . ' | php -l 2>&1', $output, $exitCode);
        $this->assertSame(0, $exitCode, "Generated prefab code has syntax errors:\n" . implode("\n", $output));
    }
}
