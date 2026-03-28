<?php

namespace Tests\Graphics\Animation;

use GL\Math\{Quat, Vec3};
use PHPUnit\Framework\TestCase;
use VISU\Graphics\Animation\AnimationChannel;
use VISU\Graphics\Animation\AnimationInterpolation;

class AnimationChannelTest extends TestCase
{
    public function testLinearTranslationInterpolation(): void
    {
        $channel = new AnimationChannel(0, 'translation');
        $channel->times = [0.0, 1.0];
        $channel->values = [
            new Vec3(0, 0, 0),
            new Vec3(10, 0, 0),
        ];

        /** @var Vec3 $result */
        $result = $channel->sample(0.5);
        $this->assertInstanceOf(Vec3::class, $result);
        $this->assertEqualsWithDelta(5.0, $result->x, 0.001);
    }

    public function testStepInterpolation(): void
    {
        $channel = new AnimationChannel(0, 'translation');
        $channel->interpolation = AnimationInterpolation::Step;
        $channel->times = [0.0, 1.0];
        $channel->values = [
            new Vec3(0, 0, 0),
            new Vec3(10, 0, 0),
        ];

        /** @var Vec3 $result */
        $result = $channel->sample(0.5);
        $this->assertEqualsWithDelta(0.0, $result->x, 0.001);
    }

    public function testClampBeforeFirstKeyframe(): void
    {
        $channel = new AnimationChannel(0, 'translation');
        $channel->times = [1.0, 2.0];
        $channel->values = [
            new Vec3(5, 0, 0),
            new Vec3(10, 0, 0),
        ];

        /** @var Vec3 $result */
        $result = $channel->sample(0.0);
        $this->assertEqualsWithDelta(5.0, $result->x, 0.001);
    }

    public function testClampAfterLastKeyframe(): void
    {
        $channel = new AnimationChannel(0, 'translation');
        $channel->times = [0.0, 1.0];
        $channel->values = [
            new Vec3(0, 0, 0),
            new Vec3(10, 0, 0),
        ];

        /** @var Vec3 $result */
        $result = $channel->sample(5.0);
        $this->assertEqualsWithDelta(10.0, $result->x, 0.001);
    }

    public function testRotationSlerp(): void
    {
        $channel = new AnimationChannel(0, 'rotation');
        $channel->times = [0.0, 1.0];
        $channel->values = [
            new Quat(1, 0, 0, 0), // identity
            new Quat(0, 0, 1, 0), // 180° around Y
        ];

        $result = $channel->sample(0.5);
        $this->assertInstanceOf(Quat::class, $result);
    }

    public function testEmptyChannelReturnsDefault(): void
    {
        $channel = new AnimationChannel(0, 'translation');

        $result = $channel->sample(0.0);
        $this->assertInstanceOf(Vec3::class, $result);
    }

    public function testEmptyRotationChannelReturnsQuat(): void
    {
        $channel = new AnimationChannel(0, 'rotation');

        $result = $channel->sample(0.0);
        $this->assertInstanceOf(Quat::class, $result);
    }
}
