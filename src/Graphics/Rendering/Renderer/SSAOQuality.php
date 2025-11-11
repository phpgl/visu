<?php

namespace VISU\Graphics\Rendering\Renderer;

class SSAOQuality
{
    public function __construct(
        public readonly float $radius,
        public readonly float $bias,
        public readonly float $strength,
        public readonly float $scale,
        public readonly float $blurScale,
        public readonly int $sampleCount,
        public readonly string $name
    ) {}

    /**
     * Low quality preset
     */
    public static function low(): self
    {
        return new self(
            radius: 0.35,
            bias: 0.035,
            strength: 3.5,
            scale: 0.5,
            blurScale: 0.5,
            sampleCount: 16,
            name: 'Low'
        );
    }

    /**
     * Medium quality preset
     */
    public static function medium(): self
    {
        return new self(
            radius: 0.5,
            bias: 0.03,
            strength: 4.0,
            scale: 0.75,
            blurScale: 1.0,
            sampleCount: 24,
            name: 'Medium'
        );
    }

    /**
     * High quality preset
     */
    public static function high(): self
    {
        return new self(
            radius: 0.65,
            bias: 0.025,
            strength: 4.5,
            scale: 1.0,
            blurScale: 1.0,
            sampleCount: 48,
            name: 'High'
        );
    }

    /**
     * Ultra quality preset
     */
    public static function ultra(): self
    {
        return new self(
            radius: 0.8,
            bias: 0.02,
            strength: 5.0,
            scale: 1.0,
            blurScale: 1.0,
            sampleCount: 64,
            name: 'Ultra'
        );
    }

    /**
     * Create a custom quality configuration
     */
    public static function custom(
        float $radius = 0.5,
        float $bias = 0.025,
        float $strength = 5.0,
        float $scale = 1.0,
        float $blurScale = 1.0,
        int $sampleCount = 32,
    ): self {
        return new self(
            radius: $radius,
            bias: $bias,
            strength: $strength,
            scale: $scale,
            blurScale: $blurScale,
            sampleCount: $sampleCount,
            name: 'Custom'
        );
    }

    /**
     * Create a quality configuration based on this one with modified parameters
     */
    public function with(
        ?float $radius = null,
        ?float $bias = null,
        ?float $strength = null,
        ?float $scale = null,
        ?float $blurScale = null,
        ?int $sampleCount = null,
        ?string $name = null
    ): self {
        return new self(
            radius: $radius ?? $this->radius,
            bias: $bias ?? $this->bias,
            strength: $strength ?? $this->strength,
            scale: $scale ?? $this->scale,
            blurScale: $blurScale ?? $this->blurScale,
            sampleCount: $sampleCount ?? $this->sampleCount,
            name: $name ?? $this->name
        );
    }
}
