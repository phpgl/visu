<?php

namespace VISU\System;

use GL\Math\Vec3;
use VISU\Component\BoxCollider3D;
use VISU\Component\CapsuleCollider3D;
use VISU\Component\SphereCollider3D;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Signal\DispatcherInterface;
use VISU\Signals\ECS\Collision3DSignal;
use VISU\Signals\ECS\TriggerSignal;

class Collision3DSystem implements SystemInterface
{
    /**
     * Spatial grid cell size for broad phase.
     */
    private float $cellSize;

    /**
     * Tracks overlapping pairs for trigger enter/stay/exit.
     *
     * @var array<string, true>
     */
    private array $activePairs = [];

    public function __construct(
        private DispatcherInterface $dispatcher,
        float $cellSize = 4.0,
    ) {
        $this->cellSize = $cellSize;
    }

    public function register(EntitiesInterface $entities): void
    {
        $entities->registerComponent(BoxCollider3D::class);
        $entities->registerComponent(SphereCollider3D::class);
        $entities->registerComponent(CapsuleCollider3D::class);
    }

    public function unregister(EntitiesInterface $entities): void
    {
    }

    public function update(EntitiesInterface $entities): void
    {
        $colliders = [];
        $this->collectBoxColliders($entities, $colliders);
        $this->collectSphereColliders($entities, $colliders);
        $this->collectCapsuleColliders($entities, $colliders);

        $candidates = $this->broadPhase($colliders);

        $currentPairs = [];
        foreach ($candidates as [$idxA, $idxB]) {
            $a = $colliders[$idxA];
            $b = $colliders[$idxB];

            if (($a['layer'] & $b['mask']) === 0 || ($b['layer'] & $a['mask']) === 0) {
                continue;
            }

            $result = $this->narrowPhase($a, $b);
            if ($result === null) {
                continue;
            }

            $entityA = $a['entity'];
            $entityB = $b['entity'];
            $pairKey = $entityA < $entityB ? "{$entityA}:{$entityB}" : "{$entityB}:{$entityA}";
            $currentPairs[$pairKey] = true;

            $isTrigger = $a['isTrigger'] || $b['isTrigger'];

            if ($isTrigger) {
                $phase = isset($this->activePairs[$pairKey]) ? TriggerSignal::STAY : TriggerSignal::ENTER;
                $this->dispatcher->dispatch('collision3d.trigger', new TriggerSignal($entityA, $entityB, $phase));
            } else {
                $this->dispatcher->dispatch('collision3d', new Collision3DSignal(
                    $entityA,
                    $entityB,
                    $result['contact'],
                    $result['normal'],
                    $result['penetration'],
                ));
            }
        }

        // trigger exit
        foreach ($this->activePairs as $pairKey => $_) {
            if (!isset($currentPairs[$pairKey])) {
                [$entityA, $entityB] = explode(':', $pairKey);
                $this->dispatcher->dispatch('collision3d.trigger', new TriggerSignal(
                    (int) $entityA,
                    (int) $entityB,
                    TriggerSignal::EXIT,
                ));
            }
        }

        $this->activePairs = $currentPairs;
    }

    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
    }

    /**
     * @param array<int, array<string, mixed>> $colliders
     */
    private function collectBoxColliders(EntitiesInterface $entities, array &$colliders): void
    {
        foreach ($entities->view(BoxCollider3D::class) as $entityId => $box) {
            $transform = $entities->tryGet($entityId, Transform::class);
            if ($transform === null) continue;

            $worldPos = $transform->getWorldPosition($entities);
            $colliders[] = [
                'entity' => $entityId,
                'type' => 'box',
                'cx' => $worldPos->x + $box->offset->x,
                'cy' => $worldPos->y + $box->offset->y,
                'cz' => $worldPos->z + $box->offset->z,
                'hx' => $box->halfExtents->x,
                'hy' => $box->halfExtents->y,
                'hz' => $box->halfExtents->z,
                'isTrigger' => $box->isTrigger,
                'layer' => $box->layer,
                'mask' => $box->mask,
            ];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $colliders
     */
    private function collectSphereColliders(EntitiesInterface $entities, array &$colliders): void
    {
        foreach ($entities->view(SphereCollider3D::class) as $entityId => $sphere) {
            $transform = $entities->tryGet($entityId, Transform::class);
            if ($transform === null) continue;

            $worldPos = $transform->getWorldPosition($entities);
            $colliders[] = [
                'entity' => $entityId,
                'type' => 'sphere',
                'cx' => $worldPos->x + $sphere->offset->x,
                'cy' => $worldPos->y + $sphere->offset->y,
                'cz' => $worldPos->z + $sphere->offset->z,
                'radius' => $sphere->radius,
                'isTrigger' => $sphere->isTrigger,
                'layer' => $sphere->layer,
                'mask' => $sphere->mask,
            ];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $colliders
     */
    private function collectCapsuleColliders(EntitiesInterface $entities, array &$colliders): void
    {
        foreach ($entities->view(CapsuleCollider3D::class) as $entityId => $capsule) {
            $transform = $entities->tryGet($entityId, Transform::class);
            if ($transform === null) continue;

            $worldPos = $transform->getWorldPosition($entities);
            $colliders[] = [
                'entity' => $entityId,
                'type' => 'capsule',
                'cx' => $worldPos->x + $capsule->offset->x,
                'cy' => $worldPos->y + $capsule->offset->y,
                'cz' => $worldPos->z + $capsule->offset->z,
                'radius' => $capsule->radius,
                'halfHeight' => $capsule->halfHeight,
                'isTrigger' => $capsule->isTrigger,
                'layer' => $capsule->layer,
                'mask' => $capsule->mask,
            ];
        }
    }

    /**
     * 3D spatial grid broad phase.
     *
     * @param array<int, array<string, mixed>> $colliders
     * @return array<array{int, int}>
     */
    private function broadPhase(array $colliders): array
    {
        /** @var array<string, array<int>> $grid */
        $grid = [];
        $inv = 1.0 / $this->cellSize;

        foreach ($colliders as $i => $c) {
            $extent = $this->getColliderExtent($c);
            $minX = (int) floor(($c['cx'] - $extent) * $inv);
            $minY = (int) floor(($c['cy'] - $extent) * $inv);
            $minZ = (int) floor(($c['cz'] - $extent) * $inv);
            $maxX = (int) floor(($c['cx'] + $extent) * $inv);
            $maxY = (int) floor(($c['cy'] + $extent) * $inv);
            $maxZ = (int) floor(($c['cz'] + $extent) * $inv);

            for ($gx = $minX; $gx <= $maxX; $gx++) {
                for ($gy = $minY; $gy <= $maxY; $gy++) {
                    for ($gz = $minZ; $gz <= $maxZ; $gz++) {
                        $key = "{$gx},{$gy},{$gz}";
                        $grid[$key][] = $i;
                    }
                }
            }
        }

        /** @var array<string, array{int, int}> $pairMap */
        $pairMap = [];
        foreach ($grid as $cell) {
            $count = count($cell);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $cell[$i];
                    $b = $cell[$j];
                    if ($colliders[$a]['entity'] === $colliders[$b]['entity']) continue;
                    $pairKey = $a < $b ? "{$a},{$b}" : "{$b},{$a}";
                    if (!isset($pairMap[$pairKey])) {
                        $pairMap[$pairKey] = [$a, $b];
                    }
                }
            }
        }

        return array_values($pairMap);
    }

    /**
     * @param array<string, mixed> $c
     */
    private function getColliderExtent(array $c): float
    {
        return match ($c['type']) {
            'box' => max($c['hx'], $c['hy'], $c['hz']),
            'sphere' => $c['radius'],
            'capsule' => max($c['radius'], $c['halfHeight'] + $c['radius']),
            default => 1.0,
        };
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array{contact: Vec3, normal: Vec3, penetration: float}|null
     */
    private function narrowPhase(array $a, array $b): ?array
    {
        $typeA = $a['type'];
        $typeB = $b['type'];

        // sphere-sphere
        if ($typeA === 'sphere' && $typeB === 'sphere') {
            return $this->testSphereSphere($a, $b);
        }

        // box-box (AABB)
        if ($typeA === 'box' && $typeB === 'box') {
            return $this->testBoxBox($a, $b);
        }

        // sphere-box
        if ($typeA === 'sphere' && $typeB === 'box') {
            return $this->testSphereBox($a, $b);
        }
        if ($typeA === 'box' && $typeB === 'sphere') {
            $result = $this->testSphereBox($b, $a);
            if ($result !== null) {
                $result['normal'] = new Vec3(-$result['normal']->x, -$result['normal']->y, -$result['normal']->z);
            }
            return $result;
        }

        // capsule-sphere
        if ($typeA === 'capsule' && $typeB === 'sphere') {
            return $this->testCapsuleSphere($a, $b);
        }
        if ($typeA === 'sphere' && $typeB === 'capsule') {
            $result = $this->testCapsuleSphere($b, $a);
            if ($result !== null) {
                $result['normal'] = new Vec3(-$result['normal']->x, -$result['normal']->y, -$result['normal']->z);
            }
            return $result;
        }

        // capsule-capsule
        if ($typeA === 'capsule' && $typeB === 'capsule') {
            return $this->testCapsuleCapsule($a, $b);
        }

        // capsule-box
        if ($typeA === 'capsule' && $typeB === 'box') {
            return $this->testCapsuleBox($a, $b);
        }
        if ($typeA === 'box' && $typeB === 'capsule') {
            $result = $this->testCapsuleBox($b, $a);
            if ($result !== null) {
                $result['normal'] = new Vec3(-$result['normal']->x, -$result['normal']->y, -$result['normal']->z);
            }
            return $result;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array{contact: Vec3, normal: Vec3, penetration: float}|null
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
        $penetration = $minDist - $dist;

        if ($dist > 1e-8) {
            $normal = new Vec3($dx / $dist, $dy / $dist, $dz / $dist);
        } else {
            $normal = new Vec3(0.0, 1.0, 0.0);
        }

        return [
            'contact' => new Vec3(
                $a['cx'] + $normal->x * $a['radius'],
                $a['cy'] + $normal->y * $a['radius'],
                $a['cz'] + $normal->z * $a['radius'],
            ),
            'normal' => $normal,
            'penetration' => $penetration,
        ];
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array{contact: Vec3, normal: Vec3, penetration: float}|null
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

        // minimum penetration axis
        $contact = new Vec3(
            ($a['cx'] + $b['cx']) * 0.5,
            ($a['cy'] + $b['cy']) * 0.5,
            ($a['cz'] + $b['cz']) * 0.5,
        );

        if ($overlapX <= $overlapY && $overlapX <= $overlapZ) {
            $sign = ($b['cx'] - $a['cx']) >= 0 ? 1.0 : -1.0;
            return ['contact' => $contact, 'normal' => new Vec3($sign, 0.0, 0.0), 'penetration' => $overlapX];
        }
        if ($overlapY <= $overlapZ) {
            $sign = ($b['cy'] - $a['cy']) >= 0 ? 1.0 : -1.0;
            return ['contact' => $contact, 'normal' => new Vec3(0.0, $sign, 0.0), 'penetration' => $overlapY];
        }
        $sign = ($b['cz'] - $a['cz']) >= 0 ? 1.0 : -1.0;
        return ['contact' => $contact, 'normal' => new Vec3(0.0, 0.0, $sign), 'penetration' => $overlapZ];
    }

    /**
     * Sphere (a) vs Box (b)
     *
     * @param array<string, mixed> $sphere
     * @param array<string, mixed> $box
     * @return array{contact: Vec3, normal: Vec3, penetration: float}|null
     */
    private function testSphereBox(array $sphere, array $box): ?array
    {
        // closest point on box to sphere center
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
        if ($dist > 1e-8) {
            $normal = new Vec3($dx / $dist, $dy / $dist, $dz / $dist);
        } else {
            $normal = new Vec3(0.0, 1.0, 0.0);
        }

        return [
            'contact' => new Vec3($closestX, $closestY, $closestZ),
            'normal' => $normal,
            'penetration' => $r - $dist,
        ];
    }

    /**
     * Capsule (a) vs Sphere (b).
     * Y-axis aligned capsule.
     *
     * @param array<string, mixed> $capsule
     * @param array<string, mixed> $sphere
     * @return array{contact: Vec3, normal: Vec3, penetration: float}|null
     */
    private function testCapsuleSphere(array $capsule, array $sphere): ?array
    {
        // closest point on capsule's line segment to sphere center
        $segY = max($capsule['cy'] - $capsule['halfHeight'], min($capsule['cy'] + $capsule['halfHeight'], $sphere['cy']));

        $dx = $sphere['cx'] - $capsule['cx'];
        $dy = $sphere['cy'] - $segY;
        $dz = $sphere['cz'] - $capsule['cz'];
        $distSq = $dx * $dx + $dy * $dy + $dz * $dz;
        $minDist = $capsule['radius'] + $sphere['radius'];

        if ($distSq > $minDist * $minDist) return null;

        $dist = sqrt($distSq);
        $penetration = $minDist - $dist;

        if ($dist > 1e-8) {
            $normal = new Vec3($dx / $dist, $dy / $dist, $dz / $dist);
        } else {
            $normal = new Vec3(0.0, 1.0, 0.0);
        }

        return [
            'contact' => new Vec3(
                $capsule['cx'] + $normal->x * $capsule['radius'],
                $segY + $normal->y * $capsule['radius'],
                $capsule['cz'] + $normal->z * $capsule['radius'],
            ),
            'normal' => $normal,
            'penetration' => $penetration,
        ];
    }

    /**
     * Capsule vs Capsule (both Y-axis aligned).
     *
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array{contact: Vec3, normal: Vec3, penetration: float}|null
     */
    private function testCapsuleCapsule(array $a, array $b): ?array
    {
        // find closest points between the two line segments (simplified Y-axis)
        $aTop = $a['cy'] + $a['halfHeight'];
        $aBot = $a['cy'] - $a['halfHeight'];
        $bTop = $b['cy'] + $b['halfHeight'];
        $bBot = $b['cy'] - $b['halfHeight'];

        // clamp each segment's center-Y to the other
        $clampedAY = max($aBot, min($aTop, $b['cy']));
        $clampedBY = max($bBot, min($bTop, $clampedAY));
        // re-clamp A to the clamped B
        $clampedAY = max($aBot, min($aTop, $clampedBY));

        $dx = $b['cx'] - $a['cx'];
        $dy = $clampedBY - $clampedAY;
        $dz = $b['cz'] - $a['cz'];
        $distSq = $dx * $dx + $dy * $dy + $dz * $dz;
        $minDist = $a['radius'] + $b['radius'];

        if ($distSq > $minDist * $minDist) return null;

        $dist = sqrt($distSq);
        $penetration = $minDist - $dist;

        if ($dist > 1e-8) {
            $normal = new Vec3($dx / $dist, $dy / $dist, $dz / $dist);
        } else {
            $normal = new Vec3(0.0, 1.0, 0.0);
        }

        return [
            'contact' => new Vec3(
                $a['cx'] + $normal->x * $a['radius'],
                $clampedAY + $normal->y * $a['radius'],
                $a['cz'] + $normal->z * $a['radius'],
            ),
            'normal' => $normal,
            'penetration' => $penetration,
        ];
    }

    /**
     * Capsule vs Box (approximate: capsule segment closest point to box, then sphere-box test).
     *
     * @param array<string, mixed> $capsule
     * @param array<string, mixed> $box
     * @return array{contact: Vec3, normal: Vec3, penetration: float}|null
     */
    private function testCapsuleBox(array $capsule, array $box): ?array
    {
        // find the point on the capsule's segment closest to the box center
        $segY = max(
            $capsule['cy'] - $capsule['halfHeight'],
            min($capsule['cy'] + $capsule['halfHeight'], $box['cy']),
        );

        // treat as sphere at that point
        $tempSphere = [
            'cx' => $capsule['cx'],
            'cy' => $segY,
            'cz' => $capsule['cz'],
            'radius' => $capsule['radius'],
        ];

        return $this->testSphereBox($tempSphere, $box);
    }
}
