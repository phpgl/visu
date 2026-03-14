<?php

namespace VISU\System;

use VISU\Component\BoxCollider2D;
use VISU\Component\CircleCollider2D;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Signal\DispatcherInterface;
use VISU\Signals\ECS\CollisionSignal;
use VISU\Signals\ECS\TriggerSignal;

class Collision2DSystem implements SystemInterface
{
    /**
     * Spatial grid cell size (larger = fewer cells but more pairs to test).
     */
    private float $cellSize = 64.0;

    /**
     * Tracks which entity pairs were overlapping last frame (for trigger enter/stay/exit).
     *
     * @var array<string, true>
     */
    private array $activePairs = [];

    public function __construct(
        private DispatcherInterface $dispatcher,
        float $cellSize = 64.0,
    ) {
        $this->cellSize = $cellSize;
    }

    public function register(EntitiesInterface $entities): void
    {
        $entities->registerComponent(BoxCollider2D::class);
        $entities->registerComponent(CircleCollider2D::class);
    }

    public function unregister(EntitiesInterface $entities): void
    {
    }

    public function update(EntitiesInterface $entities): void
    {
        // Collect all colliders with world positions
        $colliders = [];
        $this->collectBoxColliders($entities, $colliders);
        $this->collectCircleColliders($entities, $colliders);

        // Broad phase: spatial grid
        $candidates = $this->broadPhase($colliders);

        // Narrow phase: test each candidate pair
        $currentPairs = [];
        foreach ($candidates as [$idxA, $idxB]) {
            $a = $colliders[$idxA];
            $b = $colliders[$idxB];

            // Layer/mask filtering
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
                $this->dispatcher->dispatch('collision.trigger', new TriggerSignal($entityA, $entityB, $phase));
            } else {
                $this->dispatcher->dispatch('collision', new CollisionSignal(
                    $entityA,
                    $entityB,
                    $result['contactX'],
                    $result['contactY'],
                    false,
                ));
            }
        }

        // Trigger exit for pairs that were active last frame but not this frame
        foreach ($this->activePairs as $pairKey => $_) {
            if (!isset($currentPairs[$pairKey])) {
                [$entityA, $entityB] = explode(':', $pairKey);
                $this->dispatcher->dispatch('collision.trigger', new TriggerSignal(
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
        foreach ($entities->view(BoxCollider2D::class) as $entityId => $box) {
            $transform = $entities->tryGet($entityId, Transform::class);
            if ($transform === null) {
                continue;
            }

            $worldPos = $this->getWorldPosition($entities, $entityId, $transform);
            $cx = $worldPos[0] + $box->offsetX;
            $cy = $worldPos[1] + $box->offsetY;

            $colliders[] = [
                'entity' => $entityId,
                'type' => 'box',
                'cx' => $cx,
                'cy' => $cy,
                'halfW' => $box->halfWidth,
                'halfH' => $box->halfHeight,
                'isTrigger' => $box->isTrigger,
                'layer' => $box->layer,
                'mask' => $box->mask,
            ];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $colliders
     */
    private function collectCircleColliders(EntitiesInterface $entities, array &$colliders): void
    {
        foreach ($entities->view(CircleCollider2D::class) as $entityId => $circle) {
            $transform = $entities->tryGet($entityId, Transform::class);
            if ($transform === null) {
                continue;
            }

            $worldPos = $this->getWorldPosition($entities, $entityId, $transform);
            $cx = $worldPos[0] + $circle->offsetX;
            $cy = $worldPos[1] + $circle->offsetY;

            $colliders[] = [
                'entity' => $entityId,
                'type' => 'circle',
                'cx' => $cx,
                'cy' => $cy,
                'radius' => $circle->radius,
                'isTrigger' => $circle->isTrigger,
                'layer' => $circle->layer,
                'mask' => $circle->mask,
            ];
        }
    }

    /**
     * @return array{float, float}
     */
    private function getWorldPosition(EntitiesInterface $entities, int $entityId, Transform $transform): array
    {
        $x = $transform->position->x;
        $y = $transform->position->y;
        $parent = $transform->parent;
        while ($parent !== null) {
            $pt = $entities->tryGet($parent, Transform::class);
            if ($pt === null) {
                break;
            }
            $x += $pt->position->x;
            $y += $pt->position->y;
            $parent = $pt->parent;
        }
        return [$x, $y];
    }

    /**
     * Spatial grid broad phase. Returns pairs of indices into $colliders that share a grid cell.
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
            // Compute AABB for grid insertion
            if ($c['type'] === 'box') {
                $minX = $c['cx'] - $c['halfW'];
                $minY = $c['cy'] - $c['halfH'];
                $maxX = $c['cx'] + $c['halfW'];
                $maxY = $c['cy'] + $c['halfH'];
            } else {
                $r = $c['radius'];
                $minX = $c['cx'] - $r;
                $minY = $c['cy'] - $r;
                $maxX = $c['cx'] + $r;
                $maxY = $c['cy'] + $r;
            }

            $cellMinX = (int) floor($minX * $inv);
            $cellMinY = (int) floor($minY * $inv);
            $cellMaxX = (int) floor($maxX * $inv);
            $cellMaxY = (int) floor($maxY * $inv);

            for ($gx = $cellMinX; $gx <= $cellMaxX; $gx++) {
                for ($gy = $cellMinY; $gy <= $cellMaxY; $gy++) {
                    $key = "{$gx},{$gy}";
                    $grid[$key][] = $i;
                }
            }
        }

        // Collect unique pairs from cells
        /** @var array<string, array{int, int}> $pairMap */
        $pairMap = [];
        foreach ($grid as $cell) {
            $count = count($cell);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $cell[$i];
                    $b = $cell[$j];
                    // Skip self-collision (same entity with multiple colliders)
                    if ($colliders[$a]['entity'] === $colliders[$b]['entity']) {
                        continue;
                    }
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
     * Narrow phase test between two colliders.
     *
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array{contactX: float, contactY: float}|null
     */
    private function narrowPhase(array $a, array $b): ?array
    {
        $typeA = $a['type'];
        $typeB = $b['type'];

        if ($typeA === 'box' && $typeB === 'box') {
            return $this->testBoxBox($a, $b);
        }
        if ($typeA === 'circle' && $typeB === 'circle') {
            return $this->testCircleCircle($a, $b);
        }
        // box vs circle (order doesn't matter)
        if ($typeA === 'box' && $typeB === 'circle') {
            return $this->testBoxCircle($a, $b);
        }
        return $this->testBoxCircle($b, $a);
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array{contactX: float, contactY: float}|null
     */
    private function testBoxBox(array $a, array $b): ?array
    {
        $dx = abs($a['cx'] - $b['cx']);
        $dy = abs($a['cy'] - $b['cy']);
        $overlapX = $a['halfW'] + $b['halfW'] - $dx;
        $overlapY = $a['halfH'] + $b['halfH'] - $dy;

        if ($overlapX <= 0 || $overlapY <= 0) {
            return null;
        }

        return [
            'contactX' => ($a['cx'] + $b['cx']) * 0.5,
            'contactY' => ($a['cy'] + $b['cy']) * 0.5,
        ];
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array{contactX: float, contactY: float}|null
     */
    private function testCircleCircle(array $a, array $b): ?array
    {
        $dx = $b['cx'] - $a['cx'];
        $dy = $b['cy'] - $a['cy'];
        $distSq = $dx * $dx + $dy * $dy;
        $minDist = $a['radius'] + $b['radius'];

        if ($distSq > $minDist * $minDist) {
            return null;
        }

        return [
            'contactX' => ($a['cx'] + $b['cx']) * 0.5,
            'contactY' => ($a['cy'] + $b['cy']) * 0.5,
        ];
    }

    /**
     * @param array<string, mixed> $box
     * @param array<string, mixed> $circle
     * @return array{contactX: float, contactY: float}|null
     */
    private function testBoxCircle(array $box, array $circle): ?array
    {
        // Closest point on box to circle center
        $closestX = max($box['cx'] - $box['halfW'], min($circle['cx'], $box['cx'] + $box['halfW']));
        $closestY = max($box['cy'] - $box['halfH'], min($circle['cy'], $box['cy'] + $box['halfH']));

        $dx = $circle['cx'] - $closestX;
        $dy = $circle['cy'] - $closestY;
        $distSq = $dx * $dx + $dy * $dy;
        $r = $circle['radius'];

        if ($distSq > $r * $r) {
            return null;
        }

        return [
            'contactX' => $closestX,
            'contactY' => $closestY,
        ];
    }
}
