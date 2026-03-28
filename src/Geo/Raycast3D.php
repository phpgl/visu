<?php

namespace VISU\Geo;

use GL\Math\Vec3;
use VISU\Component\BoxCollider3D;
use VISU\Component\CapsuleCollider3D;
use VISU\Component\SphereCollider3D;
use VISU\ECS\EntitiesInterface;

class Raycast3D
{
    /**
     * Tests if a point is inside any 3D collider.
     *
     * @return array<int> Entity IDs containing the point.
     */
    public static function pointQuery(
        EntitiesInterface $entities,
        Vec3 $point,
        int $layerMask = 0xFFFF,
    ): array {
        $results = [];

        foreach ($entities->view(BoxCollider3D::class) as $entityId => $box) {
            if (($box->layer & $layerMask) === 0) continue;
            $transform = $entities->tryGet($entityId, Transform::class);
            if ($transform === null) continue;

            $worldPos = $transform->getWorldPosition($entities);
            $cx = $worldPos->x + $box->offset->x;
            $cy = $worldPos->y + $box->offset->y;
            $cz = $worldPos->z + $box->offset->z;

            if ($point->x >= $cx - $box->halfExtents->x && $point->x <= $cx + $box->halfExtents->x
                && $point->y >= $cy - $box->halfExtents->y && $point->y <= $cy + $box->halfExtents->y
                && $point->z >= $cz - $box->halfExtents->z && $point->z <= $cz + $box->halfExtents->z) {
                $results[] = $entityId;
            }
        }

        foreach ($entities->view(SphereCollider3D::class) as $entityId => $sphere) {
            if (($sphere->layer & $layerMask) === 0) continue;
            $transform = $entities->tryGet($entityId, Transform::class);
            if ($transform === null) continue;

            $worldPos = $transform->getWorldPosition($entities);
            $dx = $point->x - ($worldPos->x + $sphere->offset->x);
            $dy = $point->y - ($worldPos->y + $sphere->offset->y);
            $dz = $point->z - ($worldPos->z + $sphere->offset->z);

            if ($dx * $dx + $dy * $dy + $dz * $dz <= $sphere->radius * $sphere->radius) {
                $results[] = $entityId;
            }
        }

        foreach ($entities->view(CapsuleCollider3D::class) as $entityId => $capsule) {
            if (($capsule->layer & $layerMask) === 0) continue;
            $transform = $entities->tryGet($entityId, Transform::class);
            if ($transform === null) continue;

            $worldPos = $transform->getWorldPosition($entities);
            $center = new Vec3(
                $worldPos->x + $capsule->offset->x,
                $worldPos->y + $capsule->offset->y,
                $worldPos->z + $capsule->offset->z,
            );

            $distSq = self::pointCapsuleDistSq($point, $center, $capsule->halfHeight, $capsule->radius);
            if ($distSq <= $capsule->radius * $capsule->radius) {
                $results[] = $entityId;
            }
        }

        return $results;
    }

    /**
     * Casts a ray and returns the closest hit, or null.
     */
    public static function cast(
        EntitiesInterface $entities,
        Vec3 $origin,
        Vec3 $direction,
        float $maxDistance = 1000.0,
        int $layerMask = 0xFFFF,
    ): ?Raycast3DResult {
        $closest = null;
        $closestDist = $maxDistance;

        foreach ($entities->view(BoxCollider3D::class) as $entityId => $box) {
            if (($box->layer & $layerMask) === 0) continue;
            $transform = $entities->tryGet($entityId, Transform::class);
            if ($transform === null) continue;

            $worldPos = $transform->getWorldPosition($entities);
            $center = new Vec3(
                $worldPos->x + $box->offset->x,
                $worldPos->y + $box->offset->y,
                $worldPos->z + $box->offset->z,
            );

            $aabb = new AABB(
                new Vec3($center->x - $box->halfExtents->x, $center->y - $box->halfExtents->y, $center->z - $box->halfExtents->z),
                new Vec3($center->x + $box->halfExtents->x, $center->y + $box->halfExtents->y, $center->z + $box->halfExtents->z),
            );

            $ray = new Ray($origin, $direction);
            $t = $aabb->intersectRayDistance($ray);
            if ($t !== null && $t >= 0 && $t < $closestDist) {
                $hitPoint = $ray->pointAt($t);
                $closestDist = $t;
                $closest = new Raycast3DResult($entityId, $t, $hitPoint, self::aabbNormal($hitPoint, $aabb));
            }
        }

        foreach ($entities->view(SphereCollider3D::class) as $entityId => $sphere) {
            if (($sphere->layer & $layerMask) === 0) continue;
            $transform = $entities->tryGet($entityId, Transform::class);
            if ($transform === null) continue;

            $worldPos = $transform->getWorldPosition($entities);
            $center = new Vec3(
                $worldPos->x + $sphere->offset->x,
                $worldPos->y + $sphere->offset->y,
                $worldPos->z + $sphere->offset->z,
            );

            $t = self::raySphereIntersect($origin, $direction, $center, $sphere->radius);
            if ($t !== null && $t >= 0 && $t < $closestDist) {
                $hitPoint = new Vec3(
                    $origin->x + $direction->x * $t,
                    $origin->y + $direction->y * $t,
                    $origin->z + $direction->z * $t,
                );
                $normal = Vec3::normalized(new Vec3(
                    $hitPoint->x - $center->x,
                    $hitPoint->y - $center->y,
                    $hitPoint->z - $center->z,
                ));
                $closestDist = $t;
                $closest = new Raycast3DResult($entityId, $t, $hitPoint, $normal);
            }
        }

        foreach ($entities->view(CapsuleCollider3D::class) as $entityId => $capsule) {
            if (($capsule->layer & $layerMask) === 0) continue;
            $transform = $entities->tryGet($entityId, Transform::class);
            if ($transform === null) continue;

            $worldPos = $transform->getWorldPosition($entities);
            $center = new Vec3(
                $worldPos->x + $capsule->offset->x,
                $worldPos->y + $capsule->offset->y,
                $worldPos->z + $capsule->offset->z,
            );

            $t = self::rayCapsuleIntersect($origin, $direction, $center, $capsule->halfHeight, $capsule->radius);
            if ($t !== null && $t >= 0 && $t < $closestDist) {
                $hitPoint = new Vec3(
                    $origin->x + $direction->x * $t,
                    $origin->y + $direction->y * $t,
                    $origin->z + $direction->z * $t,
                );
                $normal = self::capsuleNormal($hitPoint, $center, $capsule->halfHeight);
                $closestDist = $t;
                $closest = new Raycast3DResult($entityId, $t, $hitPoint, $normal);
            }
        }

        return $closest;
    }

    /**
     * Casts a ray and returns ALL hits (sorted by distance), not just the closest.
     *
     * @return array<Raycast3DResult>
     */
    public static function castAll(
        EntitiesInterface $entities,
        Vec3 $origin,
        Vec3 $direction,
        float $maxDistance = 1000.0,
        int $layerMask = 0xFFFF,
    ): array {
        $results = [];

        foreach ($entities->view(BoxCollider3D::class) as $entityId => $box) {
            if (($box->layer & $layerMask) === 0) continue;
            $transform = $entities->tryGet($entityId, Transform::class);
            if ($transform === null) continue;

            $worldPos = $transform->getWorldPosition($entities);
            $center = new Vec3(
                $worldPos->x + $box->offset->x,
                $worldPos->y + $box->offset->y,
                $worldPos->z + $box->offset->z,
            );

            $aabb = new AABB(
                new Vec3($center->x - $box->halfExtents->x, $center->y - $box->halfExtents->y, $center->z - $box->halfExtents->z),
                new Vec3($center->x + $box->halfExtents->x, $center->y + $box->halfExtents->y, $center->z + $box->halfExtents->z),
            );

            $ray = new Ray($origin, $direction);
            $t = $aabb->intersectRayDistance($ray);
            if ($t !== null && $t >= 0 && $t <= $maxDistance) {
                $hitPoint = $ray->pointAt($t);
                $results[] = new Raycast3DResult($entityId, $t, $hitPoint, self::aabbNormal($hitPoint, $aabb));
            }
        }

        foreach ($entities->view(SphereCollider3D::class) as $entityId => $sphere) {
            if (($sphere->layer & $layerMask) === 0) continue;
            $transform = $entities->tryGet($entityId, Transform::class);
            if ($transform === null) continue;

            $worldPos = $transform->getWorldPosition($entities);
            $center = new Vec3(
                $worldPos->x + $sphere->offset->x,
                $worldPos->y + $sphere->offset->y,
                $worldPos->z + $sphere->offset->z,
            );

            $t = self::raySphereIntersect($origin, $direction, $center, $sphere->radius);
            if ($t !== null && $t >= 0 && $t <= $maxDistance) {
                $hitPoint = new Vec3(
                    $origin->x + $direction->x * $t,
                    $origin->y + $direction->y * $t,
                    $origin->z + $direction->z * $t,
                );
                $normal = Vec3::normalized(new Vec3(
                    $hitPoint->x - $center->x,
                    $hitPoint->y - $center->y,
                    $hitPoint->z - $center->z,
                ));
                $results[] = new Raycast3DResult($entityId, $t, $hitPoint, $normal);
            }
        }

        foreach ($entities->view(CapsuleCollider3D::class) as $entityId => $capsule) {
            if (($capsule->layer & $layerMask) === 0) continue;
            $transform = $entities->tryGet($entityId, Transform::class);
            if ($transform === null) continue;

            $worldPos = $transform->getWorldPosition($entities);
            $center = new Vec3(
                $worldPos->x + $capsule->offset->x,
                $worldPos->y + $capsule->offset->y,
                $worldPos->z + $capsule->offset->z,
            );

            $t = self::rayCapsuleIntersect($origin, $direction, $center, $capsule->halfHeight, $capsule->radius);
            if ($t !== null && $t >= 0 && $t <= $maxDistance) {
                $hitPoint = new Vec3(
                    $origin->x + $direction->x * $t,
                    $origin->y + $direction->y * $t,
                    $origin->z + $direction->z * $t,
                );
                $normal = self::capsuleNormal($hitPoint, $center, $capsule->halfHeight);
                $results[] = new Raycast3DResult($entityId, $t, $hitPoint, $normal);
            }
        }

        usort($results, fn(Raycast3DResult $a, Raycast3DResult $b) => $a->distance <=> $b->distance);

        return $results;
    }

    /**
     * Ray-Sphere intersection. Returns t or null.
     */
    public static function raySphereIntersect(Vec3 $origin, Vec3 $dir, Vec3 $center, float $radius): ?float
    {
        $fx = $origin->x - $center->x;
        $fy = $origin->y - $center->y;
        $fz = $origin->z - $center->z;

        $a = $dir->x * $dir->x + $dir->y * $dir->y + $dir->z * $dir->z;
        if ($a < 1e-12) return null;

        $b = 2.0 * ($fx * $dir->x + $fy * $dir->y + $fz * $dir->z);
        $c = $fx * $fx + $fy * $fy + $fz * $fz - $radius * $radius;

        $discriminant = $b * $b - 4.0 * $a * $c;
        if ($discriminant < 0) return null;

        $sqrtD = sqrt($discriminant);
        $t1 = (-$b - $sqrtD) / (2.0 * $a);
        $t2 = (-$b + $sqrtD) / (2.0 * $a);

        if ($t1 >= 0) return $t1;
        if ($t2 >= 0) return $t2;
        return null;
    }

    /**
     * Ray-Capsule intersection (Y-axis aligned capsule).
     * A capsule is a cylinder with hemisphere caps at top (y + halfHeight) and bottom (y - halfHeight).
     */
    public static function rayCapsuleIntersect(Vec3 $origin, Vec3 $dir, Vec3 $center, float $halfHeight, float $radius): ?float
    {
        $topCenter = new Vec3($center->x, $center->y + $halfHeight, $center->z);
        $botCenter = new Vec3($center->x, $center->y - $halfHeight, $center->z);

        // test infinite cylinder (XZ plane only)
        $ox = $origin->x - $center->x;
        $oz = $origin->z - $center->z;
        $a = $dir->x * $dir->x + $dir->z * $dir->z;
        $b = 2.0 * ($ox * $dir->x + $oz * $dir->z);
        $c = $ox * $ox + $oz * $oz - $radius * $radius;

        $bestT = null;

        if ($a > 1e-12) {
            $disc = $b * $b - 4.0 * $a * $c;
            if ($disc >= 0) {
                $sqrtD = sqrt($disc);
                foreach ([(-$b - $sqrtD) / (2.0 * $a), (-$b + $sqrtD) / (2.0 * $a)] as $t) {
                    if ($t < 0) continue;
                    $hitY = $origin->y + $dir->y * $t;
                    if ($hitY >= $botCenter->y && $hitY <= $topCenter->y) {
                        if ($bestT === null || $t < $bestT) $bestT = $t;
                    }
                }
            }
        }

        // test top hemisphere
        $t = self::raySphereIntersect($origin, $dir, $topCenter, $radius);
        if ($t !== null && $t >= 0) {
            $hitY = $origin->y + $dir->y * $t;
            if ($hitY >= $topCenter->y && ($bestT === null || $t < $bestT)) {
                $bestT = $t;
            }
        }

        // test bottom hemisphere
        $t = self::raySphereIntersect($origin, $dir, $botCenter, $radius);
        if ($t !== null && $t >= 0) {
            $hitY = $origin->y + $dir->y * $t;
            if ($hitY <= $botCenter->y && ($bestT === null || $t < $bestT)) {
                $bestT = $t;
            }
        }

        return $bestT;
    }

    /**
     * Squared distance from a point to the nearest point on a Y-axis capsule's line segment.
     */
    private static function pointCapsuleDistSq(Vec3 $point, Vec3 $center, float $halfHeight, float $radius): float
    {
        // clamp the point's Y projection onto the capsule's line segment
        $segY = max($center->y - $halfHeight, min($center->y + $halfHeight, $point->y));
        $dx = $point->x - $center->x;
        $dy = $point->y - $segY;
        $dz = $point->z - $center->z;
        return $dx * $dx + $dy * $dy + $dz * $dz;
    }

    /**
     * Compute surface normal for an AABB hit point (dominant axis).
     */
    private static function aabbNormal(Vec3 $hitPoint, AABB $aabb): Vec3
    {
        /** @var Vec3 $center */
        $center = $aabb->getCenter();
        $dx = $hitPoint->x - $center->x;
        $dy = $hitPoint->y - $center->y;
        $dz = $hitPoint->z - $center->z;
        $hw = $aabb->width() * 0.5;
        $hh = $aabb->height() * 0.5;
        $hd = $aabb->depth() * 0.5;

        // normalize to half-extents to find dominant face
        $ax = $hw > 0 ? abs($dx) / $hw : 0;
        $ay = $hh > 0 ? abs($dy) / $hh : 0;
        $az = $hd > 0 ? abs($dz) / $hd : 0;

        if ($ax >= $ay && $ax >= $az) {
            return new Vec3($dx > 0 ? 1.0 : -1.0, 0.0, 0.0);
        }
        if ($ay >= $az) {
            return new Vec3(0.0, $dy > 0 ? 1.0 : -1.0, 0.0);
        }
        return new Vec3(0.0, 0.0, $dz > 0 ? 1.0 : -1.0);
    }

    /**
     * Compute surface normal for a capsule hit point.
     */
    private static function capsuleNormal(Vec3 $hitPoint, Vec3 $center, float $halfHeight): Vec3
    {
        $segY = max($center->y - $halfHeight, min($center->y + $halfHeight, $hitPoint->y));
        $n = new Vec3(
            $hitPoint->x - $center->x,
            $hitPoint->y - $segY,
            $hitPoint->z - $center->z,
        );
        $len = sqrt($n->x * $n->x + $n->y * $n->y + $n->z * $n->z);
        if ($len > 1e-8) {
            $n->x /= $len;
            $n->y /= $len;
            $n->z /= $len;
        }
        return $n;
    }
}
