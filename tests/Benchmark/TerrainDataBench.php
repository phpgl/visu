<?php

namespace VISU\Tests\Benchmark;

use VISU\Graphics\Terrain\TerrainData;

class TerrainDataBench
{
    private TerrainData $smallTerrain;
    private TerrainData $largeTerrain;

    public function setUp(): void
    {
        // 64x64 terrain with random heights
        $heights = [];
        for ($i = 0; $i < 64 * 64; $i++) {
            $heights[] = sin($i * 0.1) * 0.5 + 0.5;
        }
        $this->smallTerrain = new TerrainData($heights, 64, 64, 100.0, 100.0, 20.0);

        // 256x256 terrain
        $heights = [];
        for ($i = 0; $i < 256 * 256; $i++) {
            $heights[] = sin($i * 0.01) * cos($i * 0.013) * 0.5 + 0.5;
        }
        $this->largeTerrain = new TerrainData($heights, 256, 256, 500.0, 500.0, 50.0);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchGetHeight64x64(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->smallTerrain->getHeight($i % 64, ($i * 7) % 64);
        }
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchGetHeightAtWorld64x64(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $x = ($i * 1.37) - 50.0;
            $z = ($i * 2.13) - 50.0;
            $this->smallTerrain->getHeightAtWorld($x, $z);
        }
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(100)
     * @Iterations(5)
     */
    public function benchGetHeightAtWorld256x256(): void
    {
        for ($i = 0; $i < 1000; $i++) {
            $x = ($i * 0.5) - 250.0;
            $z = ($i * 0.37) - 250.0;
            $this->largeTerrain->getHeightAtWorld($x, $z);
        }
    }

    /**
     * @Revs(10)
     * @Iterations(5)
     */
    public function benchCreateFlatTerrain256x256(): void
    {
        TerrainData::flat(256, 256, 500.0, 500.0);
    }
}
