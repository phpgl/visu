<?php

namespace VISU\System;

use GL\Math\Vec3;
use VISU\Component\BoxCollider3D;
use VISU\Component\CapsuleCollider3D;
use VISU\Component\RigidBody3D;
use VISU\Component\SphereCollider3D;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Signal\DispatcherInterface;
use VISU\Signals\ECS\Collision3DSignal;

class Physics3DSystem implements SystemInterface
{
    /**
     * World gravity (m/s^2)
     */
    public Vec3 $gravity;

    /**
     * Fixed timestep (seconds per tick). Should match GameLoop tickRate.
     */
    public float $fixedDeltaTime = 1.0 / 60.0;

    /**
     * Number of collision resolution iterations per tick.
     */
    public int $solverIterations = 4;

    /**
     * Penetration slop (small overlap allowed to prevent jitter)
     */
    public float $penetrationSlop = 0.005;

    /**
     * Baumgarte correction factor (0-1, how aggressively to resolve penetration)
     */
    public float $baumgarteFactor = 0.2;

    public function __construct(
        private DispatcherInterface $dispatcher,
        ?Vec3 $gravity = null,
    ) {
        $this->gravity = $gravity ?? new Vec3(0.0, -9.81, 0.0);
    }

    public function register(EntitiesInterface $entities): void
    {
        $entities->registerComponent(RigidBody3D::class);
    }

    public function unregister(EntitiesInterface $entities): void
    {
    }

    public function update(EntitiesInterface $entities): void
    {
        $dt = $this->fixedDeltaTime;

        // 1. Apply forces (gravity)
        $this->applyGravity($entities, $dt);

        // 2. Integrate velocities → positions (semi-implicit Euler)
        $this->integrate($entities, $dt);

        // 3. Detect and resolve collisions
        $this->detectAndResolveCollisions($entities);

        // 4. Apply damping
        $this->applyDamping($entities, $dt);
    }

    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
    }

    private function applyGravity(EntitiesInterface $entities, float $dt): void
    {
        $gravity = $this->gravity;
        foreach ($entities->view(RigidBody3D::class) as $entity => $rb) {
            if ($rb->isKinematic || $rb->mass <= 0.0) continue;

            $vel = $rb->velocity;
            $force = $rb->force;
            $gs = $rb->gravityScale;
            $m = $rb->mass;
            $vel->x = $vel->x + ($gravity->x * $gs + $force->x / $m) * $dt;
            $vel->y = $vel->y + ($gravity->y * $gs + $force->y / $m) * $dt;
            $vel->z = $vel->z + ($gravity->z * $gs + $force->z / $m) * $dt;

            // clear accumulated force
            $force->x = 0.0;
            $force->y = 0.0;
            $force->z = 0.0;
        }
    }

    private function integrate(EntitiesInterface $entities, float $dt): void
    {
        foreach ($entities->view(RigidBody3D::class) as $entity => $rb) {
            if ($rb->isKinematic || $rb->mass <= 0.0) continue;

            $transform = $entities->tryGet($entity, Transform::class);
            if ($transform === null) continue;

            $vel = $rb->velocity;
            // apply freeze constraints
            if ($rb->freezePositionX) $vel->x = 0.0;
            if ($rb->freezePositionY) $vel->y = 0.0;
            if ($rb->freezePositionZ) $vel->z = 0.0;

            $pos = $transform->position;
            $pos->x = $pos->x + $vel->x * $dt;
            $pos->y = $pos->y + $vel->y * $dt;
            $pos->z = $pos->z + $vel->z * $dt;
            $transform->markDirty();
        }
    }

    private function applyDamping(EntitiesInterface $entities, float $dt): void
    {
        foreach ($entities->view(RigidBody3D::class) as $entity => $rb) {
            if ($rb->isKinematic || $rb->mass <= 0.0) continue;

            $linearFactor = max(0.0, 1.0 - $rb->linearDrag * $dt);
            $vel = $rb->velocity;
            $vel->x = $vel->x * $linearFactor;
            $vel->y = $vel->y * $linearFactor;
            $vel->z = $vel->z * $linearFactor;

            $angularFactor = max(0.0, 1.0 - $rb->angularDrag * $dt);
            $angVel = $rb->angularVelocity;
            $angVel->x = $angVel->x * $angularFactor;
            $angVel->y = $angVel->y * $angularFactor;
            $angVel->z = $angVel->z * $angularFactor;
        }
    }

    private function detectAndResolveCollisions(EntitiesInterface $entities): void
    {
        // collect physics bodies with colliders
        $bodies = [];
        $this->collectBodies($entities, $bodies);

        if (count($bodies) < 2) return;

        for ($iter = 0; $iter < $this->solverIterations; $iter++) {
            $count = count($bodies);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $bodies[$i];
                    $b = $bodies[$j];

                    // layer/mask check
                    if (($a['layer'] & $b['mask']) === 0 || ($b['layer'] & $a['mask']) === 0) {
                        continue;
                    }

                    // both static? skip
                    $invMassA = $a['rb']->inverseMass();
                    $invMassB = $b['rb']->inverseMass();
                    if ($invMassA <= 0.0 && $invMassB <= 0.0) continue;

                    $contact = $this->testCollision($a, $b);
                    if ($contact === null) continue;

                    $this->resolveCollision($entities, $a, $b, $contact);
                }
            }

            // re-collect positions for next iteration
            if ($iter < $this->solverIterations - 1) {
                $this->refreshBodyPositions($entities, $bodies);
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $bodies
     */
    private function collectBodies(EntitiesInterface $entities, array &$bodies): void
    {
        foreach ($entities->view(RigidBody3D::class) as $entity => $rb) {
            $transform = $entities->tryGet($entity, Transform::class);
            if ($transform === null) continue;

            $worldPos = $transform->getWorldPosition($entities);

            // determine collider type and properties
            $box = $entities->tryGet($entity, BoxCollider3D::class);
            if ($box !== null) {
                $bodies[] = [
                    'entity' => $entity,
                    'rb' => $rb,
                    'transform' => $transform,
                    'type' => 'box',
                    'cx' => $worldPos->x + $box->offset->x,
                    'cy' => $worldPos->y + $box->offset->y,
                    'cz' => $worldPos->z + $box->offset->z,
                    'hx' => $box->halfExtents->x,
                    'hy' => $box->halfExtents->y,
                    'hz' => $box->halfExtents->z,
                    'layer' => $box->layer,
                    'mask' => $box->mask,
                ];
                continue;
            }

            $sphere = $entities->tryGet($entity, SphereCollider3D::class);
            if ($sphere !== null) {
                $bodies[] = [
                    'entity' => $entity,
                    'rb' => $rb,
                    'transform' => $transform,
                    'type' => 'sphere',
                    'cx' => $worldPos->x + $sphere->offset->x,
                    'cy' => $worldPos->y + $sphere->offset->y,
                    'cz' => $worldPos->z + $sphere->offset->z,
                    'radius' => $sphere->radius,
                    'layer' => $sphere->layer,
                    'mask' => $sphere->mask,
                ];
                continue;
            }

            $capsule = $entities->tryGet($entity, CapsuleCollider3D::class);
            if ($capsule !== null) {
                $bodies[] = [
                    'entity' => $entity,
                    'rb' => $rb,
                    'transform' => $transform,
                    'type' => 'capsule',
                    'cx' => $worldPos->x + $capsule->offset->x,
                    'cy' => $worldPos->y + $capsule->offset->y,
                    'cz' => $worldPos->z + $capsule->offset->z,
                    'radius' => $capsule->radius,
                    'halfHeight' => $capsule->halfHeight,
                    'layer' => $capsule->layer,
                    'mask' => $capsule->mask,
                ];
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $bodies
     */
    private function refreshBodyPositions(EntitiesInterface $entities, array &$bodies): void
    {
        foreach ($bodies as &$body) {
            $worldPos = $body['transform']->getWorldPosition($entities);
            $offset = match ($body['type']) {
                'box' => $entities->get($body['entity'], BoxCollider3D::class)->offset,
                'sphere' => $entities->get($body['entity'], SphereCollider3D::class)->offset,
                'capsule' => $entities->get($body['entity'], CapsuleCollider3D::class)->offset,
                default => new Vec3(0, 0, 0),
            };
            $body['cx'] = $worldPos->x + $offset->x;
            $body['cy'] = $worldPos->y + $offset->y;
            $body['cz'] = $worldPos->z + $offset->z;
        }
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array{normal: Vec3, penetration: float, contact: Vec3}|null
     */
    private function testCollision(array $a, array $b): ?array
    {
        $typeA = $a['type'];
        $typeB = $b['type'];

        if ($typeA === 'sphere' && $typeB === 'sphere') {
            return $this->testSphereSphere($a, $b);
        }
        if ($typeA === 'box' && $typeB === 'box') {
            return $this->testBoxBox($a, $b);
        }
        if ($typeA === 'sphere' && $typeB === 'box') {
            return $this->testSphereBox($a, $b);
        }
        if ($typeA === 'box' && $typeB === 'sphere') {
            $r = $this->testSphereBox($b, $a);
            if ($r !== null) $r['normal'] = new Vec3(-$r['normal']->x, -$r['normal']->y, -$r['normal']->z);
            return $r;
        }

        // capsule pairs
        if ($typeA === 'capsule' && $typeB === 'sphere') {
            return $this->testCapsuleSphere($a, $b);
        }
        if ($typeA === 'sphere' && $typeB === 'capsule') {
            $r = $this->testCapsuleSphere($b, $a);
            if ($r !== null) $r['normal'] = new Vec3(-$r['normal']->x, -$r['normal']->y, -$r['normal']->z);
            return $r;
        }
        if ($typeA === 'capsule' && $typeB === 'capsule') {
            return $this->testCapsuleCapsule($a, $b);
        }
        if ($typeA === 'capsule' && $typeB === 'box') {
            return $this->testCapsuleBox($a, $b);
        }
        if ($typeA === 'box' && $typeB === 'capsule') {
            $r = $this->testCapsuleBox($b, $a);
            if ($r !== null) $r['normal'] = new Vec3(-$r['normal']->x, -$r['normal']->y, -$r['normal']->z);
            return $r;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @param array{normal: Vec3, penetration: float, contact: Vec3} $contact
     */
    private function resolveCollision(EntitiesInterface $entities, array $a, array $b, array $contact): void
    {
        /** @var RigidBody3D $rbA */
        $rbA = $a['rb'];
        /** @var RigidBody3D $rbB */
        $rbB = $b['rb'];

        $invMassA = $rbA->inverseMass();
        $invMassB = $rbB->inverseMass();
        $totalInvMass = $invMassA + $invMassB;
        if ($totalInvMass <= 0.0) return;

        $normal = $contact['normal'];
        $penetration = $contact['penetration'];

        $velA = $rbA->velocity;
        $velB = $rbB->velocity;

        // relative velocity
        $relVelX = $velB->x - $velA->x;
        $relVelY = $velB->y - $velA->y;
        $relVelZ = $velB->z - $velA->z;

        $velAlongNormal = $relVelX * $normal->x + $relVelY * $normal->y + $relVelZ * $normal->z;

        // only resolve if objects are moving towards each other
        if ($velAlongNormal > 0) {
            // still need positional correction
            $this->positionalCorrection($a, $b, $invMassA, $invMassB, $totalInvMass, $normal, $penetration);
            return;
        }

        // restitution (use minimum)
        $e = min($rbA->restitution, $rbB->restitution);

        // impulse magnitude
        $j = -(1.0 + $e) * $velAlongNormal / $totalInvMass;

        // apply impulse
        $velA->x = $velA->x - $j * $invMassA * $normal->x;
        $velA->y = $velA->y - $j * $invMassA * $normal->y;
        $velA->z = $velA->z - $j * $invMassA * $normal->z;

        $velB->x = $velB->x + $j * $invMassB * $normal->x;
        $velB->y = $velB->y + $j * $invMassB * $normal->y;
        $velB->z = $velB->z + $j * $invMassB * $normal->z;

        // friction impulse (tangential)
        $tangentX = $relVelX - $velAlongNormal * $normal->x;
        $tangentY = $relVelY - $velAlongNormal * $normal->y;
        $tangentZ = $relVelZ - $velAlongNormal * $normal->z;
        $tangentLen = sqrt($tangentX * $tangentX + $tangentY * $tangentY + $tangentZ * $tangentZ);

        if ($tangentLen > 1e-8) {
            $tangentX /= $tangentLen;
            $tangentY /= $tangentLen;
            $tangentZ /= $tangentLen;

            $jt = -($relVelX * $tangentX + $relVelY * $tangentY + $relVelZ * $tangentZ) / $totalInvMass;
            $mu = min($rbA->friction, $rbB->friction);

            // Coulomb's law: clamp friction impulse
            $jt = max(-abs($j) * $mu, min(abs($j) * $mu, $jt));

            $velA->x = $velA->x - $jt * $invMassA * $tangentX;
            $velA->y = $velA->y - $jt * $invMassA * $tangentY;
            $velA->z = $velA->z - $jt * $invMassA * $tangentZ;

            $velB->x = $velB->x + $jt * $invMassB * $tangentX;
            $velB->y = $velB->y + $jt * $invMassB * $tangentY;
            $velB->z = $velB->z + $jt * $invMassB * $tangentZ;
        }

        // positional correction (Baumgarte stabilization)
        $this->positionalCorrection($a, $b, $invMassA, $invMassB, $totalInvMass, $normal, $penetration);

        // dispatch collision signal
        $this->dispatcher->dispatch('collision3d', new Collision3DSignal(
            $a['entity'],
            $b['entity'],
            $contact['contact'],
            $normal,
            $penetration,
        ));
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private function positionalCorrection(
        array $a,
        array $b,
        float $invMassA,
        float $invMassB,
        float $totalInvMass,
        Vec3 $normal,
        float $penetration,
    ): void {
        $correction = max($penetration - $this->penetrationSlop, 0.0) * $this->baumgarteFactor / $totalInvMass;

        /** @var Transform $transformA */
        $transformA = $a['transform'];
        /** @var Transform $transformB */
        $transformB = $b['transform'];

        $posA = $transformA->position;
        $posA->x = $posA->x - $correction * $invMassA * $normal->x;
        $posA->y = $posA->y - $correction * $invMassA * $normal->y;
        $posA->z = $posA->z - $correction * $invMassA * $normal->z;
        $transformA->markDirty();

        $posB = $transformB->position;
        $posB->x = $posB->x + $correction * $invMassB * $normal->x;
        $posB->y = $posB->y + $correction * $invMassB * $normal->y;
        $posB->z = $posB->z + $correction * $invMassB * $normal->z;
        $transformB->markDirty();
    }

    // -- Narrow phase (same algorithms as Collision3DSystem) --

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array{normal: Vec3, penetration: float, contact: Vec3}|null
     */
    private function testSphereSphere(array $a, array $b): ?array
    {
        $dx = $b['cx'] - $a['cx'];
        $dy = $b['cy'] - $a['cy'];
        $dz = $b['cz'] - $a['cz'];
        $distSq = $dx * $dx + $dy * $dy + $dz * $dz;
        $minDist = $a['radius'] + $b['radius'];
        if ($distSq > $minDist * $minDist) return null;

        $dist = sqrt($distSq);
        if ($dist > 1e-8) {
            $normal = new Vec3($dx / $dist, $dy / $dist, $dz / $dist);
        } else {
            $normal = new Vec3(0.0, 1.0, 0.0);
        }

        return [
            'normal' => $normal,
            'penetration' => $minDist - $dist,
            'contact' => new Vec3(
                $a['cx'] + $normal->x * $a['radius'],
                $a['cy'] + $normal->y * $a['radius'],
                $a['cz'] + $normal->z * $a['radius'],
            ),
        ];
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array{normal: Vec3, penetration: float, contact: Vec3}|null
     */
    private function testBoxBox(array $a, array $b): ?array
    {
        $dx = abs($b['cx'] - $a['cx']);
        $dy = abs($b['cy'] - $a['cy']);
        $dz = abs($b['cz'] - $a['cz']);
        $overlapX = $a['hx'] + $b['hx'] - $dx;
        $overlapY = $a['hy'] + $b['hy'] - $dy;
        $overlapZ = $a['hz'] + $b['hz'] - $dz;
        if ($overlapX <= 0 || $overlapY <= 0 || $overlapZ <= 0) return null;

        $contact = new Vec3(($a['cx'] + $b['cx']) * 0.5, ($a['cy'] + $b['cy']) * 0.5, ($a['cz'] + $b['cz']) * 0.5);

        if ($overlapX <= $overlapY && $overlapX <= $overlapZ) {
            $sign = ($b['cx'] - $a['cx']) >= 0 ? 1.0 : -1.0;
            return ['normal' => new Vec3($sign, 0, 0), 'penetration' => $overlapX, 'contact' => $contact];
        }
        if ($overlapY <= $overlapZ) {
            $sign = ($b['cy'] - $a['cy']) >= 0 ? 1.0 : -1.0;
            return ['normal' => new Vec3(0, $sign, 0), 'penetration' => $overlapY, 'contact' => $contact];
        }
        $sign = ($b['cz'] - $a['cz']) >= 0 ? 1.0 : -1.0;
        return ['normal' => new Vec3(0, 0, $sign), 'penetration' => $overlapZ, 'contact' => $contact];
    }

    /**
     * @param array<string, mixed> $sphere
     * @param array<string, mixed> $box
     * @return array{normal: Vec3, penetration: float, contact: Vec3}|null
     */
    private function testSphereBox(array $sphere, array $box): ?array
    {
        $closestX = max($box['cx'] - $box['hx'], min($sphere['cx'], $box['cx'] + $box['hx']));
        $closestY = max($box['cy'] - $box['hy'], min($sphere['cy'], $box['cy'] + $box['hy']));
        $closestZ = max($box['cz'] - $box['hz'], min($sphere['cz'], $box['cz'] + $box['hz']));

        $dx = $sphere['cx'] - $closestX;
        $dy = $sphere['cy'] - $closestY;
        $dz = $sphere['cz'] - $closestZ;
        $distSq = $dx * $dx + $dy * $dy + $dz * $dz;
        $r = $sphere['radius'];
        if ($distSq > $r * $r) return null;

        $dist = sqrt($distSq);
        $normal = $dist > 1e-8 ? new Vec3($dx / $dist, $dy / $dist, $dz / $dist) : new Vec3(0, 1, 0);

        return [
            'normal' => $normal,
            'penetration' => $r - $dist,
            'contact' => new Vec3($closestX, $closestY, $closestZ),
        ];
    }

    /**
     * @param array<string, mixed> $capsule
     * @param array<string, mixed> $sphere
     * @return array{normal: Vec3, penetration: float, contact: Vec3}|null
     */
    private function testCapsuleSphere(array $capsule, array $sphere): ?array
    {
        $segY = max($capsule['cy'] - $capsule['halfHeight'], min($capsule['cy'] + $capsule['halfHeight'], $sphere['cy']));
        $dx = $sphere['cx'] - $capsule['cx'];
        $dy = $sphere['cy'] - $segY;
        $dz = $sphere['cz'] - $capsule['cz'];
        $distSq = $dx * $dx + $dy * $dy + $dz * $dz;
        $minDist = $capsule['radius'] + $sphere['radius'];
        if ($distSq > $minDist * $minDist) return null;

        $dist = sqrt($distSq);
        $normal = $dist > 1e-8 ? new Vec3($dx / $dist, $dy / $dist, $dz / $dist) : new Vec3(0, 1, 0);

        return [
            'normal' => $normal,
            'penetration' => $minDist - $dist,
            'contact' => new Vec3(
                $capsule['cx'] + $normal->x * $capsule['radius'],
                $segY + $normal->y * $capsule['radius'],
                $capsule['cz'] + $normal->z * $capsule['radius'],
            ),
        ];
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array{normal: Vec3, penetration: float, contact: Vec3}|null
     */
    private function testCapsuleCapsule(array $a, array $b): ?array
    {
        $aTop = $a['cy'] + $a['halfHeight'];
        $aBot = $a['cy'] - $a['halfHeight'];
        $bTop = $b['cy'] + $b['halfHeight'];
        $bBot = $b['cy'] - $b['halfHeight'];

        $clampedAY = max($aBot, min($aTop, $b['cy']));
        $clampedBY = max($bBot, min($bTop, $clampedAY));
        $clampedAY = max($aBot, min($aTop, $clampedBY));

        $dx = $b['cx'] - $a['cx'];
        $dy = $clampedBY - $clampedAY;
        $dz = $b['cz'] - $a['cz'];
        $distSq = $dx * $dx + $dy * $dy + $dz * $dz;
        $minDist = $a['radius'] + $b['radius'];
        if ($distSq > $minDist * $minDist) return null;

        $dist = sqrt($distSq);
        $normal = $dist > 1e-8 ? new Vec3($dx / $dist, $dy / $dist, $dz / $dist) : new Vec3(0, 1, 0);

        return [
            'normal' => $normal,
            'penetration' => $minDist - $dist,
            'contact' => new Vec3(
                $a['cx'] + $normal->x * $a['radius'],
                $clampedAY + $normal->y * $a['radius'],
                $a['cz'] + $normal->z * $a['radius'],
            ),
        ];
    }

    /**
     * @param array<string, mixed> $capsule
     * @param array<string, mixed> $box
     * @return array{normal: Vec3, penetration: float, contact: Vec3}|null
     */
    private function testCapsuleBox(array $capsule, array $box): ?array
    {
        $segY = max($capsule['cy'] - $capsule['halfHeight'], min($capsule['cy'] + $capsule['halfHeight'], $box['cy']));
        $tempSphere = [
            'cx' => $capsule['cx'],
            'cy' => $segY,
            'cz' => $capsule['cz'],
            'radius' => $capsule['radius'],
        ];
        return $this->testSphereBox($tempSphere, $box);
    }
}
