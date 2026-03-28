<?php

namespace Tests\Component;

use PHPUnit\Framework\TestCase;
use VISU\Component\TerrainComponent;

class TerrainComponentTest extends TestCase
{
    public function testDefaults(): void
    {
        $comp = new TerrainComponent();

        $this->assertNull($comp->terrain);
        $this->assertNull($comp->blendMap);
        $this->assertCount(4, $comp->layerTextures);
        $this->assertCount(4, $comp->layerTiling);
        $this->assertTrue($comp->castsShadows);
    }

    public function testLayerTilingDefaults(): void
    {
        $comp = new TerrainComponent();

        foreach ($comp->layerTiling as $tiling) {
            $this->assertEquals(10.0, $tiling);
        }
    }
}
