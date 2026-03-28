<?php

namespace VISU\Tests\UI;

use PHPUnit\Framework\TestCase;
use VISU\UI\UIScreen;
use VISU\UI\UIScreenStack;
use VISU\UI\UITransitionType;

class UIScreenStackTest extends TestCase
{
    public function testPushAndPeek(): void
    {
        $stack = new UIScreenStack();
        $this->assertTrue($stack->isEmpty());

        $screen = new UIScreen('main_menu');
        $stack->push($screen);

        $this->assertFalse($stack->isEmpty());
        $this->assertSame(1, $stack->count());
        $this->assertSame($screen, $stack->peek());
        $this->assertTrue($screen->isActive());
    }

    public function testPop(): void
    {
        $stack = new UIScreenStack();
        $screen = new UIScreen('main_menu');
        $stack->push($screen);

        $popped = $stack->pop();
        $this->assertSame($screen, $popped);
        $this->assertFalse($screen->isActive());
        $this->assertTrue($stack->isEmpty());
    }

    public function testPopEmpty(): void
    {
        $stack = new UIScreenStack();
        $this->assertNull($stack->pop());
    }

    public function testPeekEmpty(): void
    {
        $stack = new UIScreenStack();
        $this->assertNull($stack->peek());
    }

    public function testReplace(): void
    {
        $stack = new UIScreenStack();
        $screen1 = new UIScreen('menu');
        $screen2 = new UIScreen('settings');

        $stack->push($screen1);
        $old = $stack->replace($screen2);

        $this->assertSame($screen1, $old);
        $this->assertFalse($screen1->isActive());
        $this->assertSame(1, $stack->count());
        $this->assertSame($screen2, $stack->peek());
        $this->assertTrue($screen2->isActive());
    }

    public function testMultiplePushPop(): void
    {
        $stack = new UIScreenStack();
        $s1 = new UIScreen('screen1');
        $s2 = new UIScreen('screen2');
        $s3 = new UIScreen('screen3');

        $stack->push($s1);
        $stack->push($s2);
        $stack->push($s3);
        $this->assertSame(3, $stack->count());

        $this->assertSame($s3, $stack->pop());
        $this->assertSame($s2, $stack->peek());
        $this->assertSame(2, $stack->count());
    }

    public function testClear(): void
    {
        $stack = new UIScreenStack();
        $s1 = new UIScreen('screen1');
        $s2 = new UIScreen('screen2');

        $stack->push($s1);
        $stack->push($s2);
        $stack->clear();

        $this->assertTrue($stack->isEmpty());
        $this->assertFalse($s1->isActive());
        $this->assertFalse($s2->isActive());
    }

    public function testGetScreens(): void
    {
        $stack = new UIScreenStack();
        $s1 = new UIScreen('s1');
        $s2 = new UIScreen('s2');

        $stack->push($s1);
        $stack->push($s2);

        $screens = $stack->getScreens();
        $this->assertCount(2, $screens);
        $this->assertSame($s1, $screens[0]);
        $this->assertSame($s2, $screens[1]);
    }

    public function testUpdate(): void
    {
        $stack = new UIScreenStack();
        $screen = new UIScreen('menu');
        $screen->setEnterTransition(UITransitionType::FadeIn, 1.0);
        $stack->push($screen);

        $stack->update(0.5);

        $transition = $screen->getEnterTransition();
        $this->assertNotNull($transition);
        $this->assertEqualsWithDelta(0.5, $transition->getProgress(), 0.001);
    }

    public function testScreenTransparentFlag(): void
    {
        $opaque = new UIScreen('opaque');
        $transparent = new UIScreen('overlay', transparent: true);

        $this->assertFalse($opaque->isTransparent());
        $this->assertTrue($transparent->isTransparent());
    }

    public function testScreenWithLayoutData(): void
    {
        $data = ['type' => 'panel', 'children' => []];
        $screen = new UIScreen('test', layoutData: $data);

        $this->assertSame($data, $screen->getLayoutData());
        $this->assertNull($screen->getLayoutFile());
    }

    public function testScreenSetLayout(): void
    {
        $screen = new UIScreen('test');
        $screen->setLayoutFile('/path/to/layout.json');
        $this->assertSame('/path/to/layout.json', $screen->getLayoutFile());

        $data = ['type' => 'label', 'text' => 'Hello'];
        $screen->setLayoutData($data);
        $this->assertSame($data, $screen->getLayoutData());
    }
}
