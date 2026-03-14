<?php

namespace VISU\Tests\Component;

use PHPUnit\Framework\TestCase;
use VISU\Component\Tilemap;

class TilemapTest extends TestCase
{
    public function testGetSetTile(): void
    {
        $map = new Tilemap();
        $map->width = 4;
        $map->height = 4;
        $map->tiles = array_fill(0, 16, 0);

        $map->setTile(1, 2, 5);
        $this->assertSame(5, $map->getTile(1, 2));
        $this->assertSame(0, $map->getTile(0, 0));
    }

    public function testOutOfBoundsReturnsZero(): void
    {
        $map = new Tilemap();
        $map->width = 2;
        $map->height = 2;
        $map->tiles = [1, 2, 3, 4];

        $this->assertSame(0, $map->getTile(-1, 0));
        $this->assertSame(0, $map->getTile(0, -1));
        $this->assertSame(0, $map->getTile(2, 0));
        $this->assertSame(0, $map->getTile(0, 2));
    }

    public function testGetTileUV(): void
    {
        $map = new Tilemap();
        $map->tileSize = 32;
        $map->tilesetColumns = 4;

        // Tileset 128x128, 4 columns => tile 1 is top-left
        $uv = $map->getTileUV(1, 128, 128);
        $this->assertEqualsWithDelta(0.0, $uv[0], 0.001); // u
        $this->assertEqualsWithDelta(0.0, $uv[1], 0.001); // v
        $this->assertEqualsWithDelta(0.25, $uv[2], 0.001); // w
        $this->assertEqualsWithDelta(0.25, $uv[3], 0.001); // h

        // Tile 5 is second row, first column
        $uv = $map->getTileUV(5, 128, 128);
        $this->assertEqualsWithDelta(0.0, $uv[0], 0.001);
        $this->assertEqualsWithDelta(0.25, $uv[1], 0.001);
    }

    public function testAutoTileBitmask(): void
    {
        // 3x3 grid, center tile is terrain 1, surrounded
        $map = new Tilemap();
        $map->width = 3;
        $map->height = 3;
        $map->tiles = [
            0, 1, 0,
            1, 1, 1,
            0, 1, 0,
        ];

        // Center (1,1): top=1, right=1, bottom=1, left=1 => mask = 1+2+4+8 = 15
        $this->assertSame(15, $map->getAutoTileBitmask(1, 1));

        // Top-center (1,0): no top, right=0, bottom=1, left=0 => mask = 4
        $this->assertSame(4, $map->getAutoTileBitmask(1, 0));

        // Left-center (0,1): top=0, right=1, bottom=0, left=0 => mask = 2
        $this->assertSame(2, $map->getAutoTileBitmask(0, 1));
    }

    public function testAutoTileBitmaskCornerCase(): void
    {
        // Single tile alone
        $map = new Tilemap();
        $map->width = 3;
        $map->height = 3;
        $map->tiles = [
            0, 0, 0,
            0, 1, 0,
            0, 0, 0,
        ];

        // No neighbors => mask = 0
        $this->assertSame(0, $map->getAutoTileBitmask(1, 1));
    }

    public function testResolveAutoTile(): void
    {
        $map = new Tilemap();
        $map->width = 3;
        $map->height = 3;
        $map->tiles = [
            0, 1, 0,
            1, 1, 1,
            0, 1, 0,
        ];
        $map->autoTile = true;
        $map->autoTileMap = [
            1 => [
                0  => 10, // isolated
                15 => 20, // fully surrounded
                4  => 11, // only bottom neighbor
                2  => 12, // only right neighbor
            ],
        ];

        // Center: fully surrounded => 20
        $this->assertSame(20, $map->resolveAutoTile(1, 1));

        // Top-center: only bottom neighbor => 11
        $this->assertSame(11, $map->resolveAutoTile(1, 0));

        // Left-center: only right neighbor => 12
        $this->assertSame(12, $map->resolveAutoTile(0, 1));

        // Bottom-center: mask=1 (only top), not in map => fallback to raw tile ID (1)
        $this->assertSame(1, $map->resolveAutoTile(1, 2));
    }

    public function testResolveAutoTileDisabled(): void
    {
        $map = new Tilemap();
        $map->width = 1;
        $map->height = 1;
        $map->tiles = [5];
        $map->autoTile = false;

        $this->assertSame(5, $map->resolveAutoTile(0, 0));
    }

    public function testBakeAutoTiles(): void
    {
        $map = new Tilemap();
        $map->width = 3;
        $map->height = 1;
        $map->tiles = [1, 1, 1];
        $map->autoTile = true;
        $map->autoTileMap = [
            1 => [
                2  => 10, // only right
                10 => 11, // left + right
                8  => 12, // only left
            ],
        ];

        $baked = $map->bakeAutoTiles();

        // Tile 0: right neighbor => mask=2 => 10
        $this->assertSame(10, $baked[0]);
        // Tile 1: left + right => mask=2+8=10 => 11
        $this->assertSame(11, $baked[1]);
        // Tile 2: left neighbor => mask=8 => 12
        $this->assertSame(12, $baked[2]);
    }
}
