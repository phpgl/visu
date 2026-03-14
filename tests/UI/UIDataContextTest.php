<?php

namespace VISU\Tests\UI;

use PHPUnit\Framework\TestCase;
use VISU\UI\UIDataContext;

class UIDataContextTest extends TestCase
{
    public function testSetAndGet(): void
    {
        $ctx = new UIDataContext();
        $ctx->set('economy.money', 1500);
        $this->assertSame(1500, $ctx->get('economy.money'));
    }

    public function testGetDefault(): void
    {
        $ctx = new UIDataContext();
        $this->assertNull($ctx->get('missing'));
        $this->assertSame('fallback', $ctx->get('missing', 'fallback'));
    }

    public function testSetAll(): void
    {
        $ctx = new UIDataContext();
        $ctx->setAll(['a' => 1, 'b' => 2]);
        $this->assertSame(1, $ctx->get('a'));
        $this->assertSame(2, $ctx->get('b'));
    }

    public function testResolveBindings(): void
    {
        $ctx = new UIDataContext();
        $ctx->set('economy.money', 1500);
        $ctx->set('player.name', 'Alice');

        $this->assertSame('Geld: 1500', $ctx->resolveBindings('Geld: {economy.money}'));
        $this->assertSame('Hallo Alice!', $ctx->resolveBindings('Hallo {player.name}!'));
    }

    public function testResolveBindingsFloat(): void
    {
        $ctx = new UIDataContext();
        $ctx->set('player.oxygen', 0.75);

        $this->assertSame('O2: 0.75', $ctx->resolveBindings('O2: {player.oxygen}'));
    }

    public function testResolveBindingsUnresolved(): void
    {
        $ctx = new UIDataContext();
        $this->assertSame('Value: {unknown.key}', $ctx->resolveBindings('Value: {unknown.key}'));
    }

    public function testResolveValue(): void
    {
        $ctx = new UIDataContext();
        $ctx->set('player.health', 100);

        $this->assertSame(100, $ctx->resolveValue('{player.health}'));
        $this->assertSame('plain text', $ctx->resolveValue('plain text'));
    }

    public function testHasBindings(): void
    {
        $ctx = new UIDataContext();
        $this->assertTrue($ctx->hasBindings('Hello {name}'));
        $this->assertFalse($ctx->hasBindings('Hello world'));
    }

    public function testToArray(): void
    {
        $ctx = new UIDataContext();
        $ctx->set('a', 1);
        $ctx->set('b', 'two');
        $this->assertSame(['a' => 1, 'b' => 'two'], $ctx->toArray());
    }
}
