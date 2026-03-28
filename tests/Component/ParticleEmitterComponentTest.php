<?php

namespace Tests\Component;

use GL\Math\{Vec3, Vec4};
use PHPUnit\Framework\TestCase;
use VISU\Component\ParticleEmitterComponent;
use VISU\Component\ParticleEmitterShape;

class ParticleEmitterComponentTest extends TestCase
{
    public function testDefaults(): void
    {
        $emitter = new ParticleEmitterComponent();

        $this->assertEquals(ParticleEmitterShape::Point, $emitter->shape);
        $this->assertEquals(10.0, $emitter->emissionRate);
        $this->assertEquals(0, $emitter->burstCount);
        $this->assertEquals(500, $emitter->maxParticles);
        $this->assertTrue($emitter->looping);
        $this->assertTrue($emitter->playing);
        $this->assertEquals(0.0, $emitter->gravityModifier);
        $this->assertFalse($emitter->additiveBlending);
        $this->assertNull($emitter->texture);
    }

    public function testDefaultVectors(): void
    {
        $emitter = new ParticleEmitterComponent();

        $this->assertEquals(0.0, $emitter->direction->x);
        $this->assertEquals(1.0, $emitter->direction->y);
        $this->assertEquals(0.0, $emitter->direction->z);

        $this->assertEquals(1.0, $emitter->startColor->x);
        $this->assertEquals(1.0, $emitter->startColor->w);
        $this->assertEquals(0.0, $emitter->endColor->w);
    }

    public function testSizeOverLifetime(): void
    {
        $emitter = new ParticleEmitterComponent();

        $this->assertEquals(1.0, $emitter->startSize);
        $this->assertEquals(0.0, $emitter->endSize);
    }

    public function testShapeEnum(): void
    {
        $this->assertEquals('point', ParticleEmitterShape::Point->value);
        $this->assertEquals('sphere', ParticleEmitterShape::Sphere->value);
        $this->assertEquals('cone', ParticleEmitterShape::Cone->value);
        $this->assertEquals('box', ParticleEmitterShape::Box->value);
    }
}
