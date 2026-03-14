<?php

namespace VISU\System;

use VISU\Component\SpriteAnimator;
use VISU\Component\SpriteRenderer;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Graphics\Rendering\RenderContext;

class SpriteAnimatorSystem implements SystemInterface
{
    private float $lastTime = 0.0;

    public function register(EntitiesInterface $entities): void
    {
        $entities->registerComponent(SpriteAnimator::class);
        $entities->registerComponent(SpriteRenderer::class);
    }

    public function unregister(EntitiesInterface $entities): void
    {
    }

    public function update(EntitiesInterface $entities): void
    {
        $now = microtime(true);
        $dt = $this->lastTime > 0 ? $now - $this->lastTime : 0.0;
        $this->lastTime = $now;

        if ($dt <= 0 || $dt > 1.0) {
            return;
        }

        foreach ($entities->view(SpriteAnimator::class) as $entityId => $animator) {
            if (!$animator->playing || $animator->finished) {
                continue;
            }

            $animName = $animator->currentAnimation;
            if ($animName === '' || !isset($animator->animations[$animName])) {
                continue;
            }

            $anim = $animator->animations[$animName];
            $frames = $anim['frames'];
            $fps = $anim['fps'];
            $loop = $anim['loop'];
            $frameCount = count($frames);

            if ($frameCount === 0 || $fps <= 0) {
                continue;
            }

            $animator->elapsed += $dt;
            $frameDuration = 1.0 / $fps;

            while ($animator->elapsed >= $frameDuration) {
                $animator->elapsed -= $frameDuration;
                $animator->currentFrame++;

                if ($animator->currentFrame >= $frameCount) {
                    if ($loop) {
                        $animator->currentFrame = 0;
                    } else {
                        $animator->currentFrame = $frameCount - 1;
                        $animator->finished = true;
                        $animator->playing = false;
                        break;
                    }
                }
            }

            // Apply current frame UV rect to SpriteRenderer
            $spriteRenderer = $entities->tryGet($entityId, SpriteRenderer::class);
            if ($spriteRenderer !== null && isset($frames[$animator->currentFrame])) {
                $spriteRenderer->uvRect = $frames[$animator->currentFrame];
            }
        }
    }

    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
    }
}
