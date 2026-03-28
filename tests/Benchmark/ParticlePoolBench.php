<?php

namespace VISU\Tests\Benchmark;

use VISU\Graphics\Particles\ParticlePool;

class ParticlePoolBench
{
    private ParticlePool $pool;

    public function setUp(): void
    {
        $this->pool = new ParticlePool(10000);
    }

    public function setUpFull(): void
    {
        $this->pool = new ParticlePool(10000);
        for ($i = 0; $i < 10000; $i++) {
            $this->pool->emit(
                (float)($i % 100), 0.0, (float)(intdiv($i, 100) % 100),
                0.0, 1.0, 0.0,
                1.0, 1.0, 1.0, 1.0,
                0.0, 0.0, 0.0, 0.0,
                1.0, 0.5,
                2.0,
            );
        }
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(100)
     * @Iterations(5)
     */
    public function benchEmit10kParticles(): void
    {
        for ($i = 0; $i < 10000; $i++) {
            $this->pool->emit(
                0.0, 0.0, 0.0,
                0.0, 1.0, 0.0,
                1.0, 1.0, 1.0, 1.0,
                0.0, 0.0, 0.0, 0.0,
                1.0, 0.5,
                2.0,
            );
        }
    }

    /**
     * @BeforeMethods({"setUpFull"})
     * @Revs(100)
     * @Iterations(5)
     */
    public function benchSimulate10kParticles(): void
    {
        $this->pool->simulate(0.016, 1.0, 0.0);
    }

    /**
     * @BeforeMethods({"setUpFull"})
     * @Revs(100)
     * @Iterations(5)
     */
    public function benchBuildInstanceBuffer10k(): void
    {
        $this->pool->buildInstanceBuffer();
    }
}
