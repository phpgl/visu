<?php

namespace VISU\UI;

class UITransition
{
    private float $elapsed = 0.0;
    private bool $finished = false;

    public function __construct(
        public readonly UITransitionType $type,
        public readonly float $duration = 0.3,
        public readonly float $delay = 0.0,
    ) {
    }

    public function update(float $deltaTime): void
    {
        $this->elapsed += $deltaTime;
        if ($this->elapsed >= $this->delay + $this->duration) {
            $this->finished = true;
        }
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function getProgress(): float
    {
        $active = $this->elapsed - $this->delay;
        if ($active <= 0.0) {
            return 0.0;
        }
        if ($this->duration <= 0.0) {
            return 1.0;
        }
        return min(1.0, $active / $this->duration);
    }

    /**
     * Eased progress using ease-out cubic.
     */
    public function getEasedProgress(): float
    {
        $t = $this->getProgress();
        return 1.0 - pow(1.0 - $t, 3);
    }

    /**
     * Returns the current opacity (0.0 to 1.0).
     */
    public function getOpacity(): float
    {
        $p = $this->getEasedProgress();
        return match ($this->type) {
            UITransitionType::FadeIn, UITransitionType::SlideInLeft, UITransitionType::SlideInRight,
            UITransitionType::SlideInTop, UITransitionType::SlideInBottom, UITransitionType::ScaleIn => $p,
            UITransitionType::FadeOut, UITransitionType::ScaleOut => 1.0 - $p,
        };
    }

    /**
     * Returns the X offset for slide transitions.
     */
    public function getOffsetX(float $containerWidth): float
    {
        $p = $this->getEasedProgress();
        return match ($this->type) {
            UITransitionType::SlideInLeft => -$containerWidth * (1.0 - $p),
            UITransitionType::SlideInRight => $containerWidth * (1.0 - $p),
            default => 0.0,
        };
    }

    /**
     * Returns the Y offset for slide transitions.
     */
    public function getOffsetY(float $containerHeight): float
    {
        $p = $this->getEasedProgress();
        return match ($this->type) {
            UITransitionType::SlideInTop => -$containerHeight * (1.0 - $p),
            UITransitionType::SlideInBottom => $containerHeight * (1.0 - $p),
            default => 0.0,
        };
    }

    /**
     * Returns the current scale factor (0.0 to 1.0).
     */
    public function getScale(): float
    {
        $p = $this->getEasedProgress();
        return match ($this->type) {
            UITransitionType::ScaleIn => $p,
            UITransitionType::ScaleOut => 1.0 - $p,
            default => 1.0,
        };
    }

    public function reset(): void
    {
        $this->elapsed = 0.0;
        $this->finished = false;
    }
}
