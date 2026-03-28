<?php

namespace VISU\Tests\Benchmark;

use GL\Math\Quat;
use GL\Math\Vec3;
use VISU\Graphics\Animation\AnimationChannel;
use VISU\Graphics\Animation\AnimationClip;
use VISU\Graphics\Animation\AnimationInterpolation;
use VISU\Graphics\Animation\Bone;
use VISU\Graphics\Animation\Skeleton;

class AnimationBench
{
    private AnimationChannel $translationChannel;
    private AnimationChannel $rotationChannel;
    private Skeleton $skeleton;

    public function setUp(): void
    {
        // translation channel with 60 keyframes (1 second at 60fps)
        $times = [];
        $values = [];
        for ($i = 0; $i < 60; $i++) {
            $t = $i / 60.0;
            $times[] = $t;
            $values[] = new Vec3(sin($t * 3.14) * 2.0, cos($t * 3.14), 0.0);
        }
        $this->translationChannel = new AnimationChannel(
            0, 'translation', AnimationInterpolation::Linear, $times, $values
        );

        // rotation channel with 60 keyframes
        $rotValues = [];
        for ($i = 0; $i < 60; $i++) {
            $t = $i / 60.0;
            $rotValues[] = new Quat(0.0, sin($t), 0.0, cos($t));
        }
        $this->rotationChannel = new AnimationChannel(
            0, 'rotation', AnimationInterpolation::Linear, $times, $rotValues
        );

        // skeleton with 50 bones (linear chain)
        $this->skeleton = new Skeleton();
        for ($i = 0; $i < 50; $i++) {
            $this->skeleton->addBone(new Bone($i, "bone_{$i}", $i > 0 ? $i - 1 : -1));
        }
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(10000)
     * @Iterations(5)
     */
    public function benchSampleTranslation60Keyframes(): void
    {
        $this->translationChannel->sample(0.5);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(10000)
     * @Iterations(5)
     */
    public function benchSampleRotationSlerp60Keyframes(): void
    {
        $this->rotationChannel->sample(0.5);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchSkeletonLookup50Bones(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->skeleton->getBoneByName("bone_{$i}");
        }
    }
}
