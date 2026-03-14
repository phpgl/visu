<?php

namespace Tests\Graphics\Rendering\Pass;

use GL\Math\Mat4;
use PHPUnit\Framework\TestCase;
use VISU\Graphics\Rendering\Pass\ShadowMapData;

class ShadowMapDataTest extends TestCase
{
    public function testDefaults(): void
    {
        $data = new ShadowMapData();
        $this->assertSame(4, $data->cascadeCount);
        $this->assertSame(2048, $data->resolution);
        $this->assertEmpty($data->renderTargets);
        $this->assertEmpty($data->depthTextures);
        $this->assertEmpty($data->lightSpaceMatrices);
        $this->assertEmpty($data->cascadeSplits);
    }

    public function testCascadeSplitsStorage(): void
    {
        $data = new ShadowMapData();
        $data->cascadeSplits = [10.0, 30.0, 70.0, 200.0];

        $this->assertCount(4, $data->cascadeSplits);
        $this->assertSame(10.0, $data->cascadeSplits[0]);
        $this->assertSame(200.0, $data->cascadeSplits[3]);
    }

    public function testLightSpaceMatricesStorage(): void
    {
        $data = new ShadowMapData();
        $mat = new Mat4();
        $data->lightSpaceMatrices[0] = $mat;

        $this->assertCount(1, $data->lightSpaceMatrices);
        $this->assertSame($mat, $data->lightSpaceMatrices[0]);
    }

    public function testCustomResolution(): void
    {
        $data = new ShadowMapData();
        $data->cascadeCount = 2;
        $data->resolution = 4096;

        $this->assertSame(2, $data->cascadeCount);
        $this->assertSame(4096, $data->resolution);
    }
}
