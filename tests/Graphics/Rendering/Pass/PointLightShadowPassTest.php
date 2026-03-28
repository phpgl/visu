<?php

namespace VISU\Tests\Graphics\Rendering\Pass;

use GL\Math\Vec3;
use PHPUnit\Framework\TestCase;
use VISU\Graphics\Rendering\Pass\PointLightShadowData;
use VISU\Graphics\Rendering\Pass\PointLightShadowPass;

class PointLightShadowPassTest extends TestCase
{
    public function testMaxShadowPointLightsConstant(): void
    {
        $this->assertSame(4, PointLightShadowPass::MAX_SHADOW_POINT_LIGHTS);
    }

    public function testDefaultResolutionConstant(): void
    {
        $this->assertSame(512, PointLightShadowPass::DEFAULT_RESOLUTION);
    }

    public function testShadowDataDefaults(): void
    {
        $data = new PointLightShadowData();
        $this->assertSame(512, $data->resolution);
        $this->assertSame(0, $data->shadowLightCount);
        $this->assertEmpty($data->cubemapTextureIds);
        $this->assertEmpty($data->farPlanes);
        $this->assertEmpty($data->lightPositions);
    }

    public function testShadowDataMaxLights(): void
    {
        $data = new PointLightShadowData();

        for ($i = 0; $i < PointLightShadowPass::MAX_SHADOW_POINT_LIGHTS; $i++) {
            $data->cubemapTextureIds[$i] = 100 + $i;
            $data->farPlanes[$i] = 10.0 + $i * 5.0;
            $data->lightPositions[$i] = new Vec3((float)$i, 2.0, 0.0);
        }
        $data->shadowLightCount = PointLightShadowPass::MAX_SHADOW_POINT_LIGHTS;

        $this->assertSame(4, $data->shadowLightCount);
        $this->assertCount(4, $data->cubemapTextureIds);
        $this->assertCount(4, $data->farPlanes);
        $this->assertCount(4, $data->lightPositions);
    }

    public function testShadowDataCustomResolution(): void
    {
        $data = new PointLightShadowData();
        $data->resolution = 1024;
        $this->assertSame(1024, $data->resolution);
    }

    public function testShadowDataFarPlanesMatchRange(): void
    {
        $data = new PointLightShadowData();
        $ranges = [15.0, 25.0, 50.0, 100.0];

        foreach ($ranges as $i => $range) {
            $data->farPlanes[$i] = $range;
        }

        $this->assertSame(15.0, $data->farPlanes[0]);
        $this->assertSame(100.0, $data->farPlanes[3]);
    }

    public function testShadowDataPositionStorage(): void
    {
        $data = new PointLightShadowData();
        $pos = new Vec3(5.0, 3.0, -7.0);
        $data->lightPositions[0] = $pos;

        $this->assertEqualsWithDelta(5.0, $data->lightPositions[0]->x, 0.001);
        $this->assertEqualsWithDelta(3.0, $data->lightPositions[0]->y, 0.001);
        $this->assertEqualsWithDelta(-7.0, $data->lightPositions[0]->z, 0.001);
    }

    public function testShadowDataResetBetweenFrames(): void
    {
        $data = new PointLightShadowData();
        $data->shadowLightCount = 3;
        $data->cubemapTextureIds = [1, 2, 3];
        $data->farPlanes = [10.0, 20.0, 30.0];
        $data->lightPositions = [new Vec3(0, 0, 0), new Vec3(1, 1, 1), new Vec3(2, 2, 2)];

        // Simulate frame reset (as PointLightShadowPass::execute does)
        $data->shadowLightCount = 0;
        $data->cubemapTextureIds = [];
        $data->farPlanes = [];
        $data->lightPositions = [];

        $this->assertSame(0, $data->shadowLightCount);
        $this->assertEmpty($data->cubemapTextureIds);
    }
}
