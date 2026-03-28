<?php

namespace Tests\Component;

use GL\Math\Vec3;
use PHPUnit\Framework\TestCase;
use VISU\Component\SpotLightComponent;

class SpotLightComponentTest extends TestCase
{
    public function testDefaults(): void
    {
        $light = new SpotLightComponent();

        $this->assertEquals(1.0, $light->color->x);
        $this->assertEquals(1.0, $light->color->y);
        $this->assertEquals(1.0, $light->color->z);
        $this->assertEquals(1.0, $light->intensity);
        $this->assertEquals(20.0, $light->range);
        $this->assertEquals(0.0, $light->direction->x);
        $this->assertEquals(-1.0, $light->direction->y);
        $this->assertEquals(0.0, $light->direction->z);
        $this->assertEquals(15.0, $light->innerAngle);
        $this->assertEquals(25.0, $light->outerAngle);
        $this->assertFalse($light->castsShadows);
    }

    public function testCustomValues(): void
    {
        $light = new SpotLightComponent(
            color: new Vec3(1.0, 0.0, 0.0),
            intensity: 5.0,
            range: 50.0,
            direction: new Vec3(0.0, 0.0, -1.0),
            innerAngle: 10.0,
            outerAngle: 30.0,
        );

        $this->assertEquals(1.0, $light->color->x);
        $this->assertEquals(0.0, $light->color->y);
        $this->assertEquals(5.0, $light->intensity);
        $this->assertEquals(50.0, $light->range);
        $this->assertEquals(-1.0, $light->direction->z);
        $this->assertEquals(10.0, $light->innerAngle);
        $this->assertEquals(30.0, $light->outerAngle);
    }

    public function testDefaultAttenuation(): void
    {
        $light = new SpotLightComponent();

        $this->assertEquals(1.0, $light->constantAttenuation);
        $this->assertEquals(0.09, $light->linearAttenuation);
        $this->assertEquals(0.032, $light->quadraticAttenuation);
    }

    public function testSetAttenuationFromRange(): void
    {
        $light = new SpotLightComponent(range: 50.0);
        $light->setAttenuationFromRange();

        $this->assertEquals(1.0, $light->constantAttenuation);
        $this->assertEqualsWithDelta(4.5 / 50.0, $light->linearAttenuation, 0.0001);
        $this->assertEqualsWithDelta(75.0 / 2500.0, $light->quadraticAttenuation, 0.0001);
    }
}
