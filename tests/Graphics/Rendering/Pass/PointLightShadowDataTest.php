<?php

namespace Tests\Graphics\Rendering\Pass;

use GL\Math\Vec3;
use PHPUnit\Framework\TestCase;
use VISU\Graphics\Rendering\Pass\PointLightShadowData;

class PointLightShadowDataTest extends TestCase
{
    public function testDefaults(): void
    {
        $data = new PointLightShadowData();

        $this->assertEquals(512, $data->resolution);
        $this->assertEquals(0, $data->shadowLightCount);
        $this->assertEmpty($data->cubemapTextureIds);
        $this->assertEmpty($data->farPlanes);
        $this->assertEmpty($data->lightPositions);
    }

    public function testStoreShadowLightData(): void
    {
        $data = new PointLightShadowData();
        $data->shadowLightCount = 2;
        $data->resolution = 1024;
        $data->cubemapTextureIds = [42, 43];
        $data->farPlanes = [20.0, 50.0];
        $data->lightPositions = [
            new Vec3(1.0, 2.0, 3.0),
            new Vec3(4.0, 5.0, 6.0),
        ];

        $this->assertEquals(2, $data->shadowLightCount);
        $this->assertEquals(1024, $data->resolution);
        $this->assertCount(2, $data->cubemapTextureIds);
        $this->assertEquals(42, $data->cubemapTextureIds[0]);
        $this->assertEquals(20.0, $data->farPlanes[0]);
        $this->assertEquals(50.0, $data->farPlanes[1]);
        $this->assertEquals(1.0, $data->lightPositions[0]->x);
        $this->assertEquals(5.0, $data->lightPositions[1]->y);
    }
}
