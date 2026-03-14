<?php

namespace VISU\System;

use GL\Math\{Mat4, Quat, Vec3};
use VISU\Component\SkeletalAnimationComponent;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Graphics\Animation\AnimationChannel;
use VISU\Graphics\Animation\AnimationClip;
use VISU\Graphics\Animation\Skeleton;
use VISU\Graphics\Rendering\RenderContext;

class SkeletalAnimationSystem implements SystemInterface
{
    /**
     * Fixed delta time for animation
     */
    public float $deltaTime = 1.0 / 60.0;

    public function register(EntitiesInterface $entities): void
    {
        $entities->registerComponent(SkeletalAnimationComponent::class);
    }

    public function unregister(EntitiesInterface $entities): void
    {
    }

    public function update(EntitiesInterface $entities): void
    {
        $dt = $this->deltaTime;

        foreach ($entities->view(SkeletalAnimationComponent::class) as $entity => $anim) {
            if (!$anim->playing || $anim->skeleton === null) {
                continue;
            }

            $clip = $anim->getActiveClip();
            if ($clip === null) {
                // no clip playing — compute identity bone matrices
                $this->computeBindPose($anim);
                continue;
            }

            // advance time
            $anim->time += $dt * $anim->speed;

            // handle looping/clamping
            if ($clip->duration > 0) {
                if ($anim->looping) {
                    $anim->time = fmod($anim->time, $clip->duration);
                    if ($anim->time < 0) {
                        $anim->time += $clip->duration;
                    }
                } else {
                    if ($anim->time >= $clip->duration) {
                        $anim->time = $clip->duration;
                        $anim->playing = false;
                    }
                }
            }

            $this->computeBoneMatrices($anim, $clip);
        }
    }

    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
    }

    /**
     * Computes the final bone matrices for the current animation frame.
     */
    private function computeBoneMatrices(SkeletalAnimationComponent $anim, AnimationClip $clip): void
    {
        $skeleton = $anim->skeleton;
        if ($skeleton === null) {
            return;
        }

        $boneCount = $skeleton->boneCount();

        // sample animation channels to get local transforms per bone
        /** @var array<int, Vec3> */
        $translations = [];
        /** @var array<int, Quat> */
        $rotations = [];
        /** @var array<int, Vec3> */
        $scales = [];

        foreach ($clip->channels as $channel) {
            $value = $channel->sample($anim->time);
            if ($channel->property === 'translation' && $value instanceof Vec3) {
                $translations[$channel->boneIndex] = $value;
            } elseif ($channel->property === 'rotation' && $value instanceof Quat) {
                $rotations[$channel->boneIndex] = $value;
            } elseif ($channel->property === 'scale' && $value instanceof Vec3) {
                $scales[$channel->boneIndex] = $value;
            }
        }

        // compute local transform matrices for each bone
        /** @var array<int, Mat4> */
        $localMatrices = [];
        for ($i = 0; $i < $boneCount; $i++) {
            $localMatrices[$i] = $this->composeTransform(
                $translations[$i] ?? new Vec3(0, 0, 0),
                $rotations[$i] ?? new Quat(),
                $scales[$i] ?? new Vec3(1, 1, 1),
            );
        }

        // compute world-space (model-space) transforms by walking the hierarchy
        /** @var array<int, Mat4> */
        $worldMatrices = [];
        for ($i = 0; $i < $boneCount; $i++) {
            $bone = $skeleton->bones[$i];
            if ($bone->parentIndex >= 0 && isset($worldMatrices[$bone->parentIndex])) {
                /** @var Mat4 $world */
                $world = $worldMatrices[$bone->parentIndex] * $localMatrices[$i];
                $worldMatrices[$i] = $world;
            } else {
                $worldMatrices[$i] = $localMatrices[$i];
            }
        }

        // final bone matrix = worldTransform * inverseBindMatrix
        $anim->boneMatrices = [];
        for ($i = 0; $i < $boneCount; $i++) {
            /** @var Mat4 $final */
            $final = $worldMatrices[$i] * $skeleton->bones[$i]->inverseBindMatrix;
            $anim->boneMatrices[$i] = $final;
        }
    }

    /**
     * Sets identity bone matrices (bind pose).
     */
    private function computeBindPose(SkeletalAnimationComponent $anim): void
    {
        if ($anim->skeleton === null) {
            return;
        }

        $anim->boneMatrices = [];
        for ($i = 0; $i < $anim->skeleton->boneCount(); $i++) {
            $anim->boneMatrices[$i] = new Mat4();
        }
    }

    /**
     * Composes a 4x4 transform matrix from translation, rotation, and scale.
     * Uses the same T*R*S order as Transform::getLocalMatrix().
     */
    private function composeTransform(Vec3 $translation, Quat $rotation, Vec3 $scale): Mat4
    {
        $mat = new Mat4();
        $mat->translate($translation);
        $mat = Mat4::multiplyQuat($mat, $rotation);
        $mat->scale($scale);
        return $mat;
    }
}
