<?php

namespace Tests\Component;

use PHPUnit\Framework\TestCase;
use VISU\Component\SkeletalAnimationComponent;
use VISU\Graphics\Animation\AnimationClip;

class SkeletalAnimationComponentTest extends TestCase
{
    public function testDefaults(): void
    {
        $comp = new SkeletalAnimationComponent();

        $this->assertNull($comp->skeleton);
        $this->assertNull($comp->currentClip);
        $this->assertEquals(0.0, $comp->time);
        $this->assertEquals(1.0, $comp->speed);
        $this->assertTrue($comp->looping);
        $this->assertTrue($comp->playing);
        $this->assertEmpty($comp->boneMatrices);
    }

    public function testAddAndPlayClip(): void
    {
        $comp = new SkeletalAnimationComponent();
        $clip = new AnimationClip('walk', 2.0);
        $comp->addClip($clip);

        $comp->play('walk');

        $this->assertEquals('walk', $comp->currentClip);
        $this->assertTrue($comp->playing);
        $this->assertSame($clip, $comp->getActiveClip());
    }

    public function testPlayNonexistentClipDoesNothing(): void
    {
        $comp = new SkeletalAnimationComponent();
        $comp->play('nonexistent');

        $this->assertNull($comp->currentClip);
    }

    public function testPlayWithRestart(): void
    {
        $comp = new SkeletalAnimationComponent();
        $comp->addClip(new AnimationClip('idle', 1.0));

        $comp->play('idle');
        $comp->time = 0.5;

        $comp->play('idle', true);
        $this->assertEquals(0.0, $comp->time);
    }

    public function testPlayWithoutRestart(): void
    {
        $comp = new SkeletalAnimationComponent();
        $comp->addClip(new AnimationClip('idle', 1.0));

        $comp->play('idle');
        $comp->time = 0.5;

        $comp->play('idle', false);
        $this->assertEquals(0.5, $comp->time);
    }
}
