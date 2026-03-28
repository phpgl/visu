<?php

namespace VISU\Component;

class SpriteRenderer
{
    /**
     * Path to the sprite texture (relative to assets/).
     */
    public string $sprite = '';

    /**
     * Sorting layer name for render order.
     */
    public string $sortingLayer = 'Default';

    /**
     * Order within the sorting layer (higher = rendered later / on top).
     */
    public int $orderInLayer = 0;

    /**
     * Tint color as RGBA (0.0 - 1.0).
     *
     * @var array{float, float, float, float}
     */
    public array $color = [1.0, 1.0, 1.0, 1.0];

    /**
     * Flip sprite horizontally.
     */
    public bool $flipX = false;

    /**
     * Flip sprite vertically.
     */
    public bool $flipY = false;

    /**
     * Opacity (0.0 = invisible, 1.0 = fully opaque).
     */
    public float $opacity = 1.0;

    /**
     * UV rect for sprite sheet support [x, y, width, height] in normalized coords.
     * null = use full texture.
     *
     * @var array{float, float, float, float}|null
     */
    public ?array $uvRect = null;

    /**
     * Pixel width of the sprite (0 = auto from texture).
     */
    public int $width = 0;

    /**
     * Pixel height of the sprite (0 = auto from texture).
     */
    public int $height = 0;
}
