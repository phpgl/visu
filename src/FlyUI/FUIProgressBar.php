<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\VectorGraphics\VGColor;

class FUIProgressBar extends FUIView
{
    private const DEFAULT_HEIGHT = 20.0;
    private const DEFAULT_CORNER_RADIUS = 4.0;

    public float $barHeight = self::DEFAULT_HEIGHT;
    public float $cornerRadius = self::DEFAULT_CORNER_RADIUS;
    public VGColor $fillColor;
    public VGColor $backgroundColor;

    public function __construct(
        public float $value = 0.0,
        ?VGColor $fillColor = null,
        ?VGColor $backgroundColor = null,
    ) {
        parent::__construct();
        $this->fillColor = $fillColor ?? VGColor::rgb(0.20, 0.55, 0.85);
        $this->backgroundColor = $backgroundColor ?? VGColor::rgb(0.90, 0.90, 0.90);
    }

    public function height(float $height): self
    {
        $this->barHeight = $height;
        return $this;
    }

    public function color(VGColor $color): self
    {
        $this->fillColor = $color;
        return $this;
    }

    public function getEstimatedSize(FUIRenderContext $ctx): Vec2
    {
        return new Vec2($ctx->containerSize->x, $this->barHeight);
    }

    public function render(FUIRenderContext $ctx): void
    {
        $width = $ctx->containerSize->x;
        $height = $this->barHeight;
        $ctx->containerSize->y = $height;

        $clamped = max(0.0, min(1.0, $this->value));

        // Background track
        $ctx->vg->beginPath();
        $ctx->vg->roundedRect($ctx->origin->x, $ctx->origin->y, $width, $height, $this->cornerRadius);
        $ctx->vg->fillColor($this->backgroundColor);
        $ctx->vg->fill();

        // Fill bar
        if ($clamped > 0.0) {
            $fillWidth = $width * $clamped;
            $ctx->vg->beginPath();
            $ctx->vg->roundedRect($ctx->origin->x, $ctx->origin->y, $fillWidth, $height, $this->cornerRadius);
            $ctx->vg->fillColor($this->fillColor);
            $ctx->vg->fill();
        }
    }
}
