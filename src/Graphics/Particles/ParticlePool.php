<?php

namespace VISU\Graphics\Particles;

use GL\Buffer\FloatBuffer;

class ParticlePool
{
    // --- Simulation data (structure-of-arrays) ---

    /** @var array<float> */
    public array $posX = [];
    /** @var array<float> */
    public array $posY = [];
    /** @var array<float> */
    public array $posZ = [];
    /** @var array<float> */
    public array $velX = [];
    /** @var array<float> */
    public array $velY = [];
    /** @var array<float> */
    public array $velZ = [];
    /** @var array<float> start color R */
    public array $scR = [];
    /** @var array<float> start color G */
    public array $scG = [];
    /** @var array<float> start color B */
    public array $scB = [];
    /** @var array<float> start color A */
    public array $scA = [];
    /** @var array<float> end color R */
    public array $ecR = [];
    /** @var array<float> end color G */
    public array $ecG = [];
    /** @var array<float> end color B */
    public array $ecB = [];
    /** @var array<float> end color A */
    public array $ecA = [];
    /** @var array<float> */
    public array $startSize = [];
    /** @var array<float> */
    public array $endSize = [];
    /** @var array<float> current age */
    public array $age = [];
    /** @var array<float> total lifetime */
    public array $lifetime = [];

    /**
     * Number of alive particles
     */
    public int $aliveCount = 0;

    /**
     * Maximum particle capacity
     */
    public readonly int $maxParticles;

    /**
     * Instance buffer for GPU upload.
     * Layout per particle: posX, posY, posZ, R, G, B, A, size (8 floats)
     */
    private ?FloatBuffer $instanceBuffer = null;

    public function __construct(int $maxParticles)
    {
        $this->maxParticles = $maxParticles;
    }

    /**
     * Emits a single particle at the given index.
     * Returns true if a particle slot was available.
     */
    public function emit(
        float $px,
        float $py,
        float $pz,
        float $vx,
        float $vy,
        float $vz,
        float $scR,
        float $scG,
        float $scB,
        float $scA,
        float $ecR,
        float $ecG,
        float $ecB,
        float $ecA,
        float $startSize,
        float $endSize,
        float $lifetime,
    ): bool {
        if ($this->aliveCount >= $this->maxParticles) {
            return false;
        }

        $i = $this->aliveCount;
        $this->posX[$i] = $px;
        $this->posY[$i] = $py;
        $this->posZ[$i] = $pz;
        $this->velX[$i] = $vx;
        $this->velY[$i] = $vy;
        $this->velZ[$i] = $vz;
        $this->scR[$i] = $scR;
        $this->scG[$i] = $scG;
        $this->scB[$i] = $scB;
        $this->scA[$i] = $scA;
        $this->ecR[$i] = $ecR;
        $this->ecG[$i] = $ecG;
        $this->ecB[$i] = $ecB;
        $this->ecA[$i] = $ecA;
        $this->startSize[$i] = $startSize;
        $this->endSize[$i] = $endSize;
        $this->age[$i] = 0.0;
        $this->lifetime[$i] = $lifetime;

        $this->aliveCount++;
        return true;
    }

    /**
     * Simulates all particles: integrates velocity, applies gravity and drag,
     * ages particles, and removes dead ones via swap-and-pop.
     */
    public function simulate(float $deltaTime, float $gravityModifier, float $drag): void
    {
        $gravity = -9.81 * $gravityModifier * $deltaTime;
        $dragFactor = max(0.0, 1.0 - $drag * $deltaTime);

        $i = 0;
        while ($i < $this->aliveCount) {
            $this->age[$i] += $deltaTime;

            // kill dead particles
            if ($this->age[$i] >= $this->lifetime[$i]) {
                $this->swapAndPop($i);
                continue;
            }

            // integrate velocity
            $this->velY[$i] += $gravity;

            // apply drag
            $this->velX[$i] *= $dragFactor;
            $this->velY[$i] *= $dragFactor;
            $this->velZ[$i] *= $dragFactor;

            // integrate position
            $this->posX[$i] += $this->velX[$i] * $deltaTime;
            $this->posY[$i] += $this->velY[$i] * $deltaTime;
            $this->posZ[$i] += $this->velZ[$i] * $deltaTime;

            $i++;
        }
    }

    /**
     * Builds the instance buffer for GPU upload.
     * Layout: posX, posY, posZ, R, G, B, A, size (8 floats per particle)
     */
    public function buildInstanceBuffer(): FloatBuffer
    {
        $count = $this->aliveCount;
        $data = [];

        for ($i = 0; $i < $count; $i++) {
            $t = $this->lifetime[$i] > 0.0 ? $this->age[$i] / $this->lifetime[$i] : 1.0;

            // interpolate color
            $r = $this->scR[$i] + ($this->ecR[$i] - $this->scR[$i]) * $t;
            $g = $this->scG[$i] + ($this->ecG[$i] - $this->scG[$i]) * $t;
            $b = $this->scB[$i] + ($this->ecB[$i] - $this->scB[$i]) * $t;
            $a = $this->scA[$i] + ($this->ecA[$i] - $this->scA[$i]) * $t;

            // interpolate size
            $size = $this->startSize[$i] + ($this->endSize[$i] - $this->startSize[$i]) * $t;

            $data[] = $this->posX[$i];
            $data[] = $this->posY[$i];
            $data[] = $this->posZ[$i];
            $data[] = $r;
            $data[] = $g;
            $data[] = $b;
            $data[] = $a;
            $data[] = $size;
        }

        $this->instanceBuffer = new FloatBuffer($data);
        return $this->instanceBuffer;
    }

    /**
     * Swaps particle at index with the last alive particle, then decrements alive count.
     */
    private function swapAndPop(int $index): void
    {
        $last = $this->aliveCount - 1;
        if ($index !== $last) {
            $this->posX[$index] = $this->posX[$last];
            $this->posY[$index] = $this->posY[$last];
            $this->posZ[$index] = $this->posZ[$last];
            $this->velX[$index] = $this->velX[$last];
            $this->velY[$index] = $this->velY[$last];
            $this->velZ[$index] = $this->velZ[$last];
            $this->scR[$index] = $this->scR[$last];
            $this->scG[$index] = $this->scG[$last];
            $this->scB[$index] = $this->scB[$last];
            $this->scA[$index] = $this->scA[$last];
            $this->ecR[$index] = $this->ecR[$last];
            $this->ecG[$index] = $this->ecG[$last];
            $this->ecB[$index] = $this->ecB[$last];
            $this->ecA[$index] = $this->ecA[$last];
            $this->startSize[$index] = $this->startSize[$last];
            $this->endSize[$index] = $this->endSize[$last];
            $this->age[$index] = $this->age[$last];
            $this->lifetime[$index] = $this->lifetime[$last];
        }
        $this->aliveCount--;
    }
}
