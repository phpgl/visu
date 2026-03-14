<?php

namespace Tests\Graphics\Particles;

use PHPUnit\Framework\TestCase;
use VISU\Graphics\Particles\ParticlePool;

class ParticlePoolTest extends TestCase
{
    public function testEmitAndCount(): void
    {
        $pool = new ParticlePool(100);
        $this->assertEquals(0, $pool->aliveCount);

        $result = $pool->emit(
            1.0, 2.0, 3.0,   // position
            0.0, 1.0, 0.0,   // velocity
            1.0, 1.0, 1.0, 1.0, // start color
            1.0, 0.0, 0.0, 0.0, // end color
            1.0, 0.5,          // start/end size
            2.0,               // lifetime
        );

        $this->assertTrue($result);
        $this->assertEquals(1, $pool->aliveCount);
    }

    public function testMaxCapacity(): void
    {
        $pool = new ParticlePool(3);

        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($pool->emit(0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 0, 0, 0, 0, 1, 0, 1));
        }

        // 4th should fail
        $this->assertFalse($pool->emit(0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 0, 0, 0, 0, 1, 0, 1));
        $this->assertEquals(3, $pool->aliveCount);
    }

    public function testSimulateAgesParticles(): void
    {
        $pool = new ParticlePool(10);
        $pool->emit(0, 0, 0, 1.0, 0, 0, 1, 1, 1, 1, 0, 0, 0, 0, 1, 0, 1.0);

        $pool->simulate(0.5, 0.0, 0.0);

        $this->assertEquals(1, $pool->aliveCount);
        $this->assertEqualsWithDelta(0.5, $pool->age[0], 0.001);
        $this->assertEqualsWithDelta(0.5, $pool->posX[0], 0.001); // moved by velocity
    }

    public function testSimulateKillsDeadParticles(): void
    {
        $pool = new ParticlePool(10);
        $pool->emit(0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 0, 0, 0, 0, 1, 0, 0.5);

        $pool->simulate(0.6, 0.0, 0.0); // exceeds lifetime

        $this->assertEquals(0, $pool->aliveCount);
    }

    public function testSimulateGravity(): void
    {
        $pool = new ParticlePool(10);
        $pool->emit(0, 10.0, 0, 0, 0, 0, 1, 1, 1, 1, 0, 0, 0, 0, 1, 0, 5.0);

        $pool->simulate(1.0, 1.0, 0.0); // 1 second, gravity modifier 1.0

        // velocity should be -9.81 after 1s, position should decrease
        $this->assertLessThan(10.0, $pool->posY[0]);
        $this->assertLessThan(0.0, $pool->velY[0]);
    }

    public function testSimulateDrag(): void
    {
        $pool = new ParticlePool(10);
        $pool->emit(0, 0, 0, 10.0, 0, 0, 1, 1, 1, 1, 0, 0, 0, 0, 1, 0, 5.0);

        $pool->simulate(0.1, 0.0, 2.0); // drag over short step

        // velocity should be reduced but still positive
        $this->assertLessThan(10.0, $pool->velX[0]);
        $this->assertGreaterThan(0.0, $pool->velX[0]);
    }

    public function testSwapAndPopPreservesLiveParticles(): void
    {
        $pool = new ParticlePool(10);

        // particle A: short lifetime
        $pool->emit(1, 0, 0, 0, 0, 0, 1, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0.1);
        // particle B: long lifetime
        $pool->emit(2, 0, 0, 0, 0, 0, 0, 1, 0, 1, 0, 0, 0, 0, 1, 0, 10.0);
        // particle C: long lifetime
        $pool->emit(3, 0, 0, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 1, 0, 10.0);

        $this->assertEquals(3, $pool->aliveCount);

        // kill particle A (age > 0.1)
        $pool->simulate(0.2, 0.0, 0.0);

        $this->assertEquals(2, $pool->aliveCount);

        // remaining particles should be B and C (posX = 2 and 3, order may change due to swap)
        $positions = [$pool->posX[0], $pool->posX[1]];
        sort($positions);
        $this->assertEqualsWithDelta(2.0, $positions[0], 0.1);
        $this->assertEqualsWithDelta(3.0, $positions[1], 0.1);
    }

    public function testBuildInstanceBuffer(): void
    {
        $pool = new ParticlePool(10);
        $pool->emit(1, 2, 3, 0, 0, 0, 1.0, 0.5, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 2.0, 0.0, 1.0);

        $buffer = $pool->buildInstanceBuffer();

        // 8 floats per particle
        $this->assertEquals(8, $buffer->size());

        // at t=0: color = startColor, size = startSize
        $this->assertEqualsWithDelta(1.0, $buffer[0], 0.001); // posX
        $this->assertEqualsWithDelta(2.0, $buffer[1], 0.001); // posY
        $this->assertEqualsWithDelta(3.0, $buffer[2], 0.001); // posZ
        $this->assertEqualsWithDelta(1.0, $buffer[3], 0.001); // R (start)
        $this->assertEqualsWithDelta(0.5, $buffer[4], 0.001); // G (start)
        $this->assertEqualsWithDelta(2.0, $buffer[7], 0.001); // size (start)
    }

    public function testBuildInstanceBufferInterpolatesOverLifetime(): void
    {
        $pool = new ParticlePool(10);
        $pool->emit(0, 0, 0, 0, 0, 0, 1.0, 0.0, 0.0, 1.0, 0.0, 1.0, 0.0, 0.0, 2.0, 0.0, 2.0);

        // simulate to half lifetime
        $pool->simulate(1.0, 0.0, 0.0);

        $buffer = $pool->buildInstanceBuffer();

        // at t=0.5: color should be (0.5, 0.5, 0, 0.5), size = 1.0
        $this->assertEqualsWithDelta(0.5, $buffer[3], 0.001); // R
        $this->assertEqualsWithDelta(0.5, $buffer[4], 0.001); // G
        $this->assertEqualsWithDelta(0.5, $buffer[6], 0.001); // A
        $this->assertEqualsWithDelta(1.0, $buffer[7], 0.001); // size
    }
}
