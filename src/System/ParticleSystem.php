<?php

namespace VISU\System;

use GL\Math\{GLM, Vec3};
use VISU\Component\ParticleEmitterComponent;
use VISU\Component\ParticleEmitterShape;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\Transform;
use VISU\Graphics\Particles\ParticlePool;
use VISU\Graphics\Rendering\RenderContext;

class ParticleSystem implements SystemInterface
{
    /**
     * Particle pools per entity
     * @var array<int, ParticlePool>
     */
    private array $pools = [];

    /**
     * Fixed delta time for simulation
     */
    public float $deltaTime = 1.0 / 60.0;

    public function register(EntitiesInterface $entities): void
    {
        $entities->registerComponent(ParticleEmitterComponent::class);
        $entities->registerComponent(Transform::class);
    }

    public function unregister(EntitiesInterface $entities): void
    {
    }

    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
    }

    public function update(EntitiesInterface $entities): void
    {
        $dt = $this->deltaTime;

        foreach ($entities->view(ParticleEmitterComponent::class) as $entity => $emitter) {
            // ensure pool exists
            if (!isset($this->pools[$entity])) {
                $this->pools[$entity] = new ParticlePool($emitter->maxParticles);
            }

            $pool = $this->pools[$entity];
            $transform = $entities->get($entity, Transform::class);
            $worldPos = $transform->getWorldPosition($entities);

            if ($emitter->playing) {
                $emitter->elapsedTime += $dt;

                // initial burst
                if (!$emitter->burstEmitted && $emitter->burstCount > 0) {
                    $this->emitParticles($emitter, $pool, $worldPos, $emitter->burstCount);
                    $emitter->burstEmitted = true;
                }

                // rate-based emission
                if ($emitter->emissionRate > 0) {
                    $emitter->emissionAccumulator += $dt * $emitter->emissionRate;
                    $toEmit = (int)$emitter->emissionAccumulator;
                    if ($toEmit > 0) {
                        $emitter->emissionAccumulator -= $toEmit;
                        $this->emitParticles($emitter, $pool, $worldPos, $toEmit);
                    }
                }

                // handle duration for non-looping emitters
                if (!$emitter->looping && $emitter->elapsedTime >= $emitter->duration) {
                    $emitter->playing = false;
                }
            }

            // simulate particles
            $pool->simulate($dt, $emitter->gravityModifier, $emitter->drag);
        }

        // clean up pools for destroyed entities
        foreach ($this->pools as $entity => $pool) {
            if (!$entities->valid($entity)) {
                unset($this->pools[$entity]);
            }
        }
    }

    /**
     * Returns the particle pool for a given entity, or null if none exists.
     */
    public function getPool(int $entity): ?ParticlePool
    {
        return $this->pools[$entity] ?? null;
    }

    /**
     * Returns all active pools indexed by entity ID.
     * @return array<int, ParticlePool>
     */
    public function getPools(): array
    {
        return $this->pools;
    }

    private function emitParticles(
        ParticleEmitterComponent $emitter,
        ParticlePool $pool,
        Vec3 $worldPos,
        int $count,
    ): void {
        for ($n = 0; $n < $count; $n++) {
            [$ox, $oy, $oz] = $this->computeSpawnOffset($emitter);
            [$dx, $dy, $dz] = $this->computeDirection($emitter);

            $speed = $this->randomRange($emitter->speedMin, $emitter->speedMax);
            $lifetime = $this->randomRange($emitter->lifetimeMin, $emitter->lifetimeMax);

            $pool->emit(
                $worldPos->x + $ox,
                $worldPos->y + $oy,
                $worldPos->z + $oz,
                $dx * $speed,
                $dy * $speed,
                $dz * $speed,
                $emitter->startColor->x,
                $emitter->startColor->y,
                $emitter->startColor->z,
                $emitter->startColor->w,
                $emitter->endColor->x,
                $emitter->endColor->y,
                $emitter->endColor->z,
                $emitter->endColor->w,
                $emitter->startSize,
                $emitter->endSize,
                $lifetime,
            );
        }
    }

    /**
     * Computes a spawn position offset based on the emitter shape.
     * @return array{float, float, float}
     */
    private function computeSpawnOffset(ParticleEmitterComponent $emitter): array
    {
        return match ($emitter->shape) {
            ParticleEmitterShape::Point => [0.0, 0.0, 0.0],
            ParticleEmitterShape::Sphere => $this->randomSpherePoint($emitter->sphereRadius),
            ParticleEmitterShape::Cone => [0.0, 0.0, 0.0], // cone emits from apex
            ParticleEmitterShape::Box => [
                $this->randomRange(-$emitter->boxHalfExtents->x, $emitter->boxHalfExtents->x),
                $this->randomRange(-$emitter->boxHalfExtents->y, $emitter->boxHalfExtents->y),
                $this->randomRange(-$emitter->boxHalfExtents->z, $emitter->boxHalfExtents->z),
            ],
        };
    }

    /**
     * Computes a normalized emission direction based on the emitter settings.
     * @return array{float, float, float}
     */
    private function computeDirection(ParticleEmitterComponent $emitter): array
    {
        if ($emitter->shape === ParticleEmitterShape::Sphere) {
            return $this->randomUnitSphere();
        }

        if ($emitter->shape === ParticleEmitterShape::Cone) {
            return $this->randomConeDirection(
                $emitter->direction->x,
                $emitter->direction->y,
                $emitter->direction->z,
                $emitter->coneAngle,
            );
        }

        // Point/Box: use direction with optional randomness
        $dx = $emitter->direction->x;
        $dy = $emitter->direction->y;
        $dz = $emitter->direction->z;

        if ($emitter->directionRandomness > 0.0) {
            [$rx, $ry, $rz] = $this->randomUnitSphere();
            $r = $emitter->directionRandomness;
            $dx = $dx * (1.0 - $r) + $rx * $r;
            $dy = $dy * (1.0 - $r) + $ry * $r;
            $dz = $dz * (1.0 - $r) + $rz * $r;

            // renormalize
            $len = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
            if ($len > 0.0001) {
                $dx /= $len;
                $dy /= $len;
                $dz /= $len;
            }
        }

        return [$dx, $dy, $dz];
    }

    /**
     * @return array{float, float, float}
     */
    private function randomUnitSphere(): array
    {
        // uniform random point on unit sphere
        $theta = $this->randomRange(0.0, 2.0 * M_PI);
        $phi = acos($this->randomRange(-1.0, 1.0));
        $sinPhi = sin($phi);
        return [
            $sinPhi * cos($theta),
            $sinPhi * sin($theta),
            cos($phi),
        ];
    }

    /**
     * @return array{float, float, float}
     */
    private function randomSpherePoint(float $radius): array
    {
        [$x, $y, $z] = $this->randomUnitSphere();
        $r = $radius * pow($this->randomRange(0.0, 1.0), 1.0 / 3.0);
        return [$x * $r, $y * $r, $z * $r];
    }

    /**
     * @return array{float, float, float}
     */
    private function randomConeDirection(float $dx, float $dy, float $dz, float $angleDeg): array
    {
        $angleRad = GLM::radians($angleDeg);
        $cosAngle = cos($angleRad);

        // random point in cone around (0,0,1), then rotate to match direction
        $z = $this->randomRange($cosAngle, 1.0);
        $phi = $this->randomRange(0.0, 2.0 * M_PI);
        $sinZ = sqrt(1.0 - $z * $z);

        $lx = $sinZ * cos($phi);
        $ly = $sinZ * sin($phi);
        $lz = $z;

        // build rotation from (0,0,1) to (dx, dy, dz)
        $len = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
        if ($len < 0.0001) {
            return [$lx, $ly, $lz];
        }
        $dx /= $len;
        $dy /= $len;
        $dz /= $len;

        // find rotation axis and angle (cross product of (0,0,1) and dir)
        $cx = -$dy; // (0,0,1) x (dx,dy,dz)
        $cy = $dx;
        $cz = 0.0;
        $cLen = sqrt($cx * $cx + $cy * $cy);

        if ($cLen < 0.0001) {
            // direction is (anti-)parallel to Z
            return $dz > 0 ? [$lx, $ly, $lz] : [-$lx, -$ly, -$lz];
        }

        $cx /= $cLen;
        $cy /= $cLen;

        $dot = $dz; // dot((0,0,1), dir)
        $cosA = $dot;
        $sinA = $cLen;

        // Rodrigues' rotation: v' = v*cosA + (k x v)*sinA + k*(k.v)*(1-cosA)
        $kdotV = $cx * $lx + $cy * $ly; // cz is 0
        $kxVx = $cy * $lz;
        $kxVy = -$cx * $lz;
        $kxVz = $cx * $ly - $cy * $lx;

        $rx = $lx * $cosA + $kxVx * $sinA + $cx * $kdotV * (1.0 - $cosA);
        $ry = $ly * $cosA + $kxVy * $sinA + $cy * $kdotV * (1.0 - $cosA);
        $rz = $lz * $cosA + $kxVz * $sinA + 0.0;

        return [$rx, $ry, $rz];
    }

    private function randomRange(float $min, float $max): float
    {
        return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
    }
}
