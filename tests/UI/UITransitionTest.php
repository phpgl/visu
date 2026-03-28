<?php

namespace VISU\Tests\UI;

use PHPUnit\Framework\TestCase;
use VISU\UI\UITransition;
use VISU\UI\UITransitionType;

class UITransitionTest extends TestCase
{
    public function testFadeInProgress(): void
    {
        $t = new UITransition(UITransitionType::FadeIn, 1.0);
        $this->assertEqualsWithDelta(0.0, $t->getProgress(), 0.001);

        $t->update(0.5);
        $this->assertEqualsWithDelta(0.5, $t->getProgress(), 0.001);

        $t->update(0.5);
        $this->assertEqualsWithDelta(1.0, $t->getProgress(), 0.001);
        $this->assertTrue($t->isFinished());
    }

    public function testFadeInOpacity(): void
    {
        $t = new UITransition(UITransitionType::FadeIn, 1.0);
        $this->assertEqualsWithDelta(0.0, $t->getOpacity(), 0.001);

        $t->update(1.0);
        $this->assertEqualsWithDelta(1.0, $t->getOpacity(), 0.001);
    }

    public function testFadeOutOpacity(): void
    {
        $t = new UITransition(UITransitionType::FadeOut, 1.0);
        $t->update(0.0);
        $this->assertEqualsWithDelta(1.0, $t->getOpacity(), 0.001);

        $t->update(1.0);
        $this->assertEqualsWithDelta(0.0, $t->getOpacity(), 0.001);
    }

    public function testSlideInLeftOffset(): void
    {
        $t = new UITransition(UITransitionType::SlideInLeft, 1.0);
        $this->assertEqualsWithDelta(-800.0, $t->getOffsetX(800.0), 1.0);

        $t->update(1.0);
        $this->assertEqualsWithDelta(0.0, $t->getOffsetX(800.0), 0.1);
    }

    public function testSlideInRightOffset(): void
    {
        $t = new UITransition(UITransitionType::SlideInRight, 1.0);
        $this->assertEqualsWithDelta(800.0, $t->getOffsetX(800.0), 1.0);

        $t->update(1.0);
        $this->assertEqualsWithDelta(0.0, $t->getOffsetX(800.0), 0.1);
    }

    public function testSlideInTopOffset(): void
    {
        $t = new UITransition(UITransitionType::SlideInTop, 1.0);
        $this->assertEqualsWithDelta(-600.0, $t->getOffsetY(600.0), 1.0);

        $t->update(1.0);
        $this->assertEqualsWithDelta(0.0, $t->getOffsetY(600.0), 0.1);
    }

    public function testSlideInBottomOffset(): void
    {
        $t = new UITransition(UITransitionType::SlideInBottom, 1.0);
        $this->assertEqualsWithDelta(600.0, $t->getOffsetY(600.0), 1.0);

        $t->update(1.0);
        $this->assertEqualsWithDelta(0.0, $t->getOffsetY(600.0), 0.1);
    }

    public function testScaleInScale(): void
    {
        $t = new UITransition(UITransitionType::ScaleIn, 1.0);
        $this->assertEqualsWithDelta(0.0, $t->getScale(), 0.001);

        $t->update(1.0);
        $this->assertEqualsWithDelta(1.0, $t->getScale(), 0.001);
    }

    public function testScaleOutScale(): void
    {
        $t = new UITransition(UITransitionType::ScaleOut, 1.0);
        $t->update(0.0);
        $this->assertEqualsWithDelta(1.0, $t->getScale(), 0.001);

        $t->update(1.0);
        $this->assertEqualsWithDelta(0.0, $t->getScale(), 0.001);
    }

    public function testDelay(): void
    {
        $t = new UITransition(UITransitionType::FadeIn, 1.0, 0.5);

        $t->update(0.25);
        $this->assertEqualsWithDelta(0.0, $t->getProgress(), 0.001);
        $this->assertFalse($t->isFinished());

        $t->update(0.25);
        $this->assertEqualsWithDelta(0.0, $t->getProgress(), 0.001);

        $t->update(0.5);
        $this->assertEqualsWithDelta(0.5, $t->getProgress(), 0.001);

        $t->update(0.5);
        $this->assertTrue($t->isFinished());
    }

    public function testReset(): void
    {
        $t = new UITransition(UITransitionType::FadeIn, 1.0);
        $t->update(1.0);
        $this->assertTrue($t->isFinished());

        $t->reset();
        $this->assertFalse($t->isFinished());
        $this->assertEqualsWithDelta(0.0, $t->getProgress(), 0.001);
    }

    public function testEasedProgressNonLinear(): void
    {
        $t = new UITransition(UITransitionType::FadeIn, 1.0);
        $t->update(0.5);

        // Ease-out cubic at 0.5: 1 - (1-0.5)^3 = 1 - 0.125 = 0.875
        $this->assertEqualsWithDelta(0.875, $t->getEasedProgress(), 0.001);
    }

    public function testNoOffsetForNonSlideTransitions(): void
    {
        $t = new UITransition(UITransitionType::FadeIn, 1.0);
        $t->update(0.5);
        $this->assertEqualsWithDelta(0.0, $t->getOffsetX(800.0), 0.001);
        $this->assertEqualsWithDelta(0.0, $t->getOffsetY(600.0), 0.001);
    }

    public function testScaleIsOneForNonScaleTransitions(): void
    {
        $t = new UITransition(UITransitionType::FadeIn, 1.0);
        $t->update(0.5);
        $this->assertEqualsWithDelta(1.0, $t->getScale(), 0.001);
    }
}
