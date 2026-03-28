<?php

namespace Tests\Component;

use GL\Math\Vec3;
use PHPUnit\Framework\TestCase;
use VISU\Component\PointLightComponent;

/**
 * Tests for PointLightComponent shadow-related functionality.
 */
class PointLightShadowTest extends TestCase
{
    public function testShadowsDisabledByDefault(): void
    {
        $light = new PointLightComponent();
        $this->assertFalse($light->castsShadows);
    }

    public function testEnableShadows(): void
    {
        $light = new PointLightComponent();
        $light->castsShadows = true;
        $this->assertTrue($light->castsShadows);
    }

    public function testShadowLightWithCustomRange(): void
    {
        $light = new PointLightComponent(
            color: new Vec3(1.0, 0.8, 0.6),
            intensity: 5.0,
            range: 30.0,
        );
        $light->castsShadows = true;
        $light->setAttenuationFromRange();

        $this->assertTrue($light->castsShadows);
        $this->assertEquals(30.0, $light->range);
        $this->assertEqualsWithDelta(0.15, $light->linearAttenuation, 0.001);
    }

    public function testMultipleShadowLights(): void
    {
        $lights = [];
        $colors = [
            new Vec3(1, 0, 0),
            new Vec3(0, 1, 0),
            new Vec3(0, 0, 1),
            new Vec3(1, 1, 0),
        ];

        foreach ($colors as $i => $color) {
            $light = new PointLightComponent($color, 2.0, 15.0 + $i * 5.0);
            $light->castsShadows = true;
            $light->setAttenuationFromRange();
            $lights[] = $light;
        }

        $this->assertCount(4, $lights);
        foreach ($lights as $light) {
            $this->assertTrue($light->castsShadows);
        }
        $this->assertEquals(15.0, $lights[0]->range);
        $this->assertEquals(30.0, $lights[3]->range);
    }

    public function testRangeAsFarPlane(): void
    {
        // The range doubles as the far plane for cubemap shadow projection
        $light = new PointLightComponent(range: 50.0);
        $light->castsShadows = true;

        // Far plane equals range
        $this->assertEquals(50.0, $light->range);
    }

    public function testMixedShadowAndNonShadowLights(): void
    {
        $shadowLight = new PointLightComponent(new Vec3(1, 1, 1), 3.0, 20.0);
        $shadowLight->castsShadows = true;

        $noShadowLight = new PointLightComponent(new Vec3(1, 0.5, 0), 1.0, 10.0);
        // castsShadows defaults to false

        $this->assertTrue($shadowLight->castsShadows);
        $this->assertFalse($noShadowLight->castsShadows);
    }
}
