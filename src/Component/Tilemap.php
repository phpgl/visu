<?php

namespace VISU\Component;

class Tilemap
{
    /**
     * Width of the tilemap in tiles.
     */
    public int $width = 0;

    /**
     * Height of the tilemap in tiles.
     */
    public int $height = 0;

    /**
     * Size of each tile in pixels.
     */
    public int $tileSize = 32;

    /**
     * Tileset texture path (relative to assets/).
     */
    public string $tileset = '';

    /**
     * Tileset columns (how many tiles per row in the tileset image).
     */
    public int $tilesetColumns = 1;

    /**
     * Sorting layer for render order.
     */
    public string $sortingLayer = 'Background';

    /**
     * Tile data as a flat array. Index = y * width + x.
     * Value = tile ID (0 = empty, 1+ = tileset index).
     *
     * @var array<int>
     */
    public array $tiles = [];

    /**
     * Gets a tile at grid position.
     */
    public function getTile(int $x, int $y): int
    {
        if ($x < 0 || $x >= $this->width || $y < 0 || $y >= $this->height) {
            return 0;
        }

        return $this->tiles[$y * $this->width + $x] ?? 0;
    }

    /**
     * Sets a tile at grid position.
     */
    public function setTile(int $x, int $y, int $tileId): void
    {
        if ($x < 0 || $x >= $this->width || $y < 0 || $y >= $this->height) {
            return;
        }

        $this->tiles[$y * $this->width + $x] = $tileId;
    }

    /**
     * Whether auto-tiling is enabled for this tilemap.
     * When enabled, tile IDs represent terrain types and the actual tileset index
     * is computed from the 4-bit bitmask of matching neighbors.
     */
    public bool $autoTile = false;

    /**
     * Auto-tile mapping: terrain type => bitmask => tileset index.
     * Bitmask bits: 1=top, 2=right, 4=bottom, 8=left.
     * This gives 16 possible combinations (0-15) per terrain type.
     *
     * Example: [1 => [0 => 1, 1 => 2, 2 => 3, ...]]
     * means terrain type 1 with no neighbors uses tileset index 1, etc.
     *
     * @var array<int, array<int, int>>
     */
    public array $autoTileMap = [];

    /**
     * Computes the UV rect for a tile ID in the tileset.
     *
     * @return array{float, float, float, float} [u, v, w, h] in normalized coordinates
     */
    public function getTileUV(int $tileId, int $tilesetWidth, int $tilesetHeight): array
    {
        if ($tileId <= 0 || $this->tilesetColumns <= 0) {
            return [0, 0, 0, 0];
        }

        $index = $tileId - 1; // tile IDs are 1-based
        $col = $index % $this->tilesetColumns;
        $row = (int)($index / $this->tilesetColumns);

        $tileW = $this->tileSize / $tilesetWidth;
        $tileH = $this->tileSize / $tilesetHeight;

        return [
            $col * $tileW,
            $row * $tileH,
            $tileW,
            $tileH,
        ];
    }

    /**
     * Computes a 4-bit neighbor bitmask for auto-tiling.
     * Bits: 1=top, 2=right, 4=bottom, 8=left.
     * A bit is set if the neighbor has the same terrain type.
     */
    public function getAutoTileBitmask(int $x, int $y): int
    {
        $terrainType = $this->getTile($x, $y);
        if ($terrainType <= 0) {
            return 0;
        }

        $mask = 0;
        if ($this->getTile($x, $y - 1) === $terrainType) $mask |= 1; // top
        if ($this->getTile($x + 1, $y) === $terrainType) $mask |= 2; // right
        if ($this->getTile($x, $y + 1) === $terrainType) $mask |= 4; // bottom
        if ($this->getTile($x - 1, $y) === $terrainType) $mask |= 8; // left

        return $mask;
    }

    /**
     * Resolves the actual tileset index for an auto-tiled position.
     * Returns the mapped tileset index or falls back to the raw tile ID.
     */
    public function resolveAutoTile(int $x, int $y): int
    {
        $terrainType = $this->getTile($x, $y);
        if ($terrainType <= 0) {
            return 0;
        }

        if (!$this->autoTile || !isset($this->autoTileMap[$terrainType])) {
            return $terrainType;
        }

        $mask = $this->getAutoTileBitmask($x, $y);
        return $this->autoTileMap[$terrainType][$mask] ?? $terrainType;
    }

    /**
     * Recalculates all auto-tile mappings and returns a resolved tile grid.
     * Useful for baking the auto-tile results into a flat array for fast rendering.
     *
     * @return array<int> Resolved tileset indices in the same flat layout as $tiles.
     */
    public function bakeAutoTiles(): array
    {
        if (!$this->autoTile) {
            return $this->tiles;
        }

        $result = [];
        for ($y = 0; $y < $this->height; $y++) {
            for ($x = 0; $x < $this->width; $x++) {
                $result[$y * $this->width + $x] = $this->resolveAutoTile($x, $y);
            }
        }

        return $result;
    }
}
