<?php

namespace VISU\Tests\ECS;

use PHPUnit\Framework\TestCase;
use VISU\Component\NameComponent;
use VISU\Component\SpriteRenderer;
use VISU\ECS\ComponentRegistry;
use VISU\ECS\Exception\ECSException;

class ComponentRegistryTest extends TestCase
{
    private ComponentRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ComponentRegistry();
    }

    public function testRegisterAndResolve(): void
    {
        $this->registry->register('SpriteRenderer', SpriteRenderer::class);
        $this->assertSame(SpriteRenderer::class, $this->registry->resolve('SpriteRenderer'));
    }

    public function testHas(): void
    {
        $this->assertFalse($this->registry->has('SpriteRenderer'));
        $this->registry->register('SpriteRenderer', SpriteRenderer::class);
        $this->assertTrue($this->registry->has('SpriteRenderer'));
    }

    public function testResolveUnknownThrows(): void
    {
        $this->expectException(ECSException::class);
        $this->registry->resolve('NonExistent');
    }

    public function testRegisterInvalidClassThrows(): void
    {
        $this->expectException(ECSException::class);
        $this->registry->register('Fake', 'VISU\\Component\\NonExistent');
    }

    public function testCreate(): void
    {
        $this->registry->register('SpriteRenderer', SpriteRenderer::class);
        $component = $this->registry->create('SpriteRenderer', [
            'sprite' => 'test.png',
            'sortingLayer' => 'Foreground',
            'opacity' => 0.5,
        ]);

        $this->assertInstanceOf(SpriteRenderer::class, $component);
        /** @var SpriteRenderer $component */
        $this->assertSame('test.png', $component->sprite);
        $this->assertSame('Foreground', $component->sortingLayer);
        $this->assertSame(0.5, $component->opacity);
    }

    public function testCreateIgnoresUnknownProperties(): void
    {
        $this->registry->register('NameComponent', NameComponent::class);
        $component = $this->registry->create('NameComponent', [
            'name' => 'Test',
            'nonExistent' => 'value',
        ]);

        $this->assertInstanceOf(NameComponent::class, $component);
        /** @var NameComponent $component */
        $this->assertSame('Test', $component->name);
    }

    public function testGetTypeName(): void
    {
        $this->registry->register('SpriteRenderer', SpriteRenderer::class);
        $this->assertSame('SpriteRenderer', $this->registry->getTypeName(SpriteRenderer::class));
        $this->assertNull($this->registry->getTypeName(NameComponent::class));
    }

    public function testGetRegisteredTypes(): void
    {
        $this->registry->register('SpriteRenderer', SpriteRenderer::class);
        $this->registry->register('NameComponent', NameComponent::class);

        $types = $this->registry->getRegisteredTypes();
        $this->assertContains('SpriteRenderer', $types);
        $this->assertContains('NameComponent', $types);
        $this->assertCount(2, $types);
    }
}
