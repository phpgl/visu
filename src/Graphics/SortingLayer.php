<?php

namespace VISU\Graphics;

class SortingLayer
{
    /**
     * Default sorting layers in render order.
     */
    public const DEFAULT_LAYERS = [
        'Background' => 0,
        'Default' => 100,
        'Foreground' => 200,
        'UI' => 300,
    ];

    /**
     * Registered layers: name => order.
     *
     * @var array<string, int>
     */
    private array $layers;

    public function __construct()
    {
        $this->layers = self::DEFAULT_LAYERS;
    }

    /**
     * Adds or updates a sorting layer.
     */
    public function add(string $name, int $order): void
    {
        $this->layers[$name] = $order;
    }

    /**
     * Returns the sort order for a given layer name.
     */
    public function getOrder(string $name): int
    {
        return $this->layers[$name] ?? 100;
    }

    /**
     * Computes a combined sort key from layer + orderInLayer + Y position.
     * Lower values are rendered first (behind higher values).
     */
    public function getSortKey(string $layerName, int $orderInLayer, float $yPosition = 0.0): int
    {
        $layerOrder = $this->getOrder($layerName);
        // Layer (high bits) | orderInLayer (mid bits) | inverted Y for top-down sorting (low bits)
        // Y is inverted so that entities lower on screen (higher Y in screen space) render on top
        $yKey = (int)((-$yPosition + 100000) * 10);
        return ($layerOrder << 24) | (($orderInLayer + 32768) << 12) | ($yKey & 0xFFF);
    }

    /**
     * @return array<string, int>
     */
    public function getLayers(): array
    {
        return $this->layers;
    }
}
