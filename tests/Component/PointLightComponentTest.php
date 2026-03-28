<?php

namespace Tests\Component;

use GL\Math\Vec3;
use PHPUnit\Framework\TestCase;
use VISU\Component\PointLightComponent;

class PointLightComponentTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $light = new PointLightComponent();
        $this->assertEquals(1.0, $light->color->x);
        $this->assertEquals(1.0, $light->color->y);
        $this->assertEquals(1.0, $light->color->z);
        $this->assertEquals(1.0, $light->intensity);
        $this->assertEquals(20.0, $light->range);
        $this->assertFalse($light->castsShadows);
    }

    public function testCustomConstructor(): void
    {
        $light = new PointLightComponent(
            color: new Vec3(1.0, 0.5, 0.0),
            intensity: 2.5,
            range: 50.0,
        );
        $this->assertEquals(1.0, $light->color->x);
        $this->assertEquals(0.5, $light->color->y);
        $this->assertEquals(0.0, $light->color->z);
        $this->assertEquals(2.5, $light->intensity);
        $this->assertEquals(50.0, $light->range);
    }

    public function testDefaultAttenuation(): void
    {
        $light = new PointLightComponent();
        $this->assertEquals(1.0, $light->constantAttenuation);
        $this->assertEquals(0.09, $light->linearAttenuation);
        $this->assertEquals(0.032, $light->quadraticAttenuation);
    }

    public function testSetAttenuationFromRange(): void
    {
        $light = new PointLightComponent(range: 10.0);
        $light->setAttenuationFromRange();

        $this->assertEquals(1.0, $light->constantAttenuation);
        $this->assertEqualsWithDelta(0.45, $light->linearAttenuation, 0.001);
        $this->assertEqualsWithDelta(0.75, $light->quadraticAttenuation, 0.001);
    }

    public function testSetAttenuationFromLargeRange(): void
    {
        $light = new PointLightComponent(range: 100.0);
        $light->setAttenuationFromRange();

        $this->assertEqualsWithDelta(0.045, $light->linearAttenuation, 0.001);
        $this->assertEqualsWithDelta(0.0075, $light->quadraticAttenuation, 0.001);
    }
}
