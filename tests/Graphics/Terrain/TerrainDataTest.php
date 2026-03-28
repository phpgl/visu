<?php

namespace Tests\Graphics\Terrain;

use PHPUnit\Framework\TestCase;
use VISU\Graphics\Terrain\TerrainData;

class TerrainDataTest extends TestCase
{
    public function testFlatTerrain(): void
    {
        $data = TerrainData::flat(10, 10, 100.0, 100.0);

        $this->assertEquals(10, $data->width);
        $this->assertEquals(10, $data->depth);
        $this->assertEquals(100.0, $data->sizeX);
        $this->assertEquals(100.0, $data->sizeZ);
        $this->assertEquals(0.0, $data->getHeight(5, 5));
    }

    public function testGetHeightClampsToGrid(): void
    {
        $heights = [0.0, 0.5, 1.0, 0.0, 0.5, 1.0, 0.0, 0.5, 1.0];
        $data = new TerrainData($heights, 3, 3, 10.0, 10.0, 10.0);

        // within bounds
        $this->assertEqualsWithDelta(5.0, $data->getHeight(1, 0), 0.001);
        $this->assertEqualsWithDelta(10.0, $data->getHeight(2, 0), 0.001);

        // clamped to bounds
        $this->assertEqualsWithDelta(0.0, $data->getHeight(-1, 0), 0.001);
        $this->assertEqualsWithDelta(10.0, $data->getHeight(99, 0), 0.001);
    }

    public function testGetHeightAtWorldCenter(): void
    {
        $heights = array_fill(0, 9, 0.5);
        $data = new TerrainData($heights, 3, 3, 10.0, 10.0, 20.0);

        // center of terrain
        $this->assertEqualsWithDelta(10.0, $data->getHeightAtWorld(0.0, 0.0), 0.001);
    }

    public function testGetHeightAtWorldInterpolation(): void
    {
        // 2x2 grid: corners have different heights
        $heights = [0.0, 1.0, 0.0, 1.0];
        $data = new TerrainData($heights, 2, 2, 10.0, 10.0, 10.0);

        // center should average to 5.0
        $this->assertEqualsWithDelta(5.0, $data->getHeightAtWorld(0.0, 0.0), 0.001);

        // left edge center
        $this->assertEqualsWithDelta(0.0, $data->getHeightAtWorld(-5.0, 0.0), 0.001);

        // right edge center
        $this->assertEqualsWithDelta(10.0, $data->getHeightAtWorld(5.0, 0.0), 0.001);
    }

    public function testGetRawHeights(): void
    {
        $heights = [0.1, 0.2, 0.3, 0.4];
        $data = new TerrainData($heights, 2, 2);

        $this->assertEquals($heights, $data->getRawHeights());
    }
}
