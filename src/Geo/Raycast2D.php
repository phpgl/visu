<?php

namespace VISU\Geo;

use VISU\Component\BoxCollider2D;
use VISU\Component\CircleCollider2D;
use VISU\ECS\EntitiesInterface;

class Raycast2D
{
    /**
     * Result of a raycast hit.
     *
     * @param int $entityId
     * @param float $distance
     * @param float $hitX
     * @param float $hitY
     */
    public static function hit(int $entityId, float $distance, float $hitX, float $hitY): Raycast2DResult
    {
        return new Raycast2DResult($entityId, $distance, $hitX, $hitY);
    }

    /**
     * Tests if a point is inside any collider and returns matching entity IDs.
     *
     * @return array<int> Entity IDs containing the point.
     */
    public static function pointQuery(
        EntitiesInterface $entities,
        float $px,
        float $py,
        int $layerMask = 0xFFFF,
    ): array {
        $results = [];

        foreach ($entities->view(BoxCollider2D::class) as $entityId => $box) {
            if (($box->layer & $layerMask) === 0) {
                continue;
            }
            $transform = $entities->tryGet($entityId, Transform::class);
            if ($transform === null) {
                continue;
            }
            $worldPos = self::getWorldPos($entities, $entityId, $transform);
            $cx = $worldPos[0] + $box->offsetX;
            $cy = $worldPos[1] + $box->offsetY;

            if ($px >= $cx - $box->halfWidth && $px <= $cx + $box->halfWidth
                && $py >= $cy - $box->halfHeight && $py <= $cy + $box->halfHeight) {
                $results[] = $entityId;
            }
        }

        foreach ($entities->view(CircleCollider2D::class) as $entityId => $circle) {
            if (($circle->layer & $layerMask) === 0) {
                continue;
            }
            $transform = $entities->tryGet($entityId, Transform::class);
            if ($transform === null) {
                continue;
            }
            $worldPos = self::getWorldPos($entities, $entityId, $transform);
            $cx = $worldPos[0] + $circle->offsetX;
            $cy = $worldPos[1] + $circle->offsetY;

            $dx = $px - $cx;
            $dy = $py - $cy;
            if ($dx * $dx + $dy * $dy <= $circle->radius * $circle->radius) {
                $results[] = $entityId;
            }
        }

        return $results;
    }

    /**
     * Casts a ray and returns the closest hit, or null.
     *
     * @param float $originX Ray start X
     * @param float $originY Ray start Y
     * @param float $dirX Ray direction X (should be normalized)
     * @param float $dirY Ray direction Y (should be normalized)
     * @param float $maxDistance Maximum distance to test
     */
    public static function cast(
        EntitiesInterface $entities,
        float $originX,
        float $originY,
        float $dirX,
        float $dirY,
        float $maxDistance = 1000.0,
        int $layerMask = 0xFFFF,
    ): ?Raycast2DResult {
        $closest = null;
        $closestDist = $maxDistance;

        foreach ($entities->view(BoxCollider2D::class) as $entityId => $box) {
            if (($box->layer & $layerMask) === 0) {
                continue;
            }
            $transform = $entities->tryGet($entityId, Transform::class);
            if ($transform === null) {
                continue;
            }
            $worldPos = self::getWorldPos($entities, $entityId, $transform);
            $cx = $worldPos[0] + $box->offsetX;
            $cy = $worldPos[1] + $box->offsetY;

            $t = self::rayBoxIntersect(
                $originX, $originY, $dirX, $dirY,
                $cx - $box->halfWidth, $cy - $box->halfHeight,
                $cx + $box->halfWidth, $cy + $box->halfHeight,
            );

            if ($t !== null && $t >= 0 && $t < $closestDist) {
                $closestDist = $t;
                $closest = new Raycast2DResult(
                    $entityId,
                    $t,
                    $originX + $dirX * $t,
                    $originY + $dirY * $t,
                );
            }
        }

        foreach ($entities->view(CircleCollider2D::class) as $entityId => $circle) {
            if (($circle->layer & $layerMask) === 0) {
                continue;
            }
            $transform = $entities->tryGet($entityId, Transform::class);
            if ($transform === null) {
                continue;
            }
            $worldPos = self::getWorldPos($entities, $entityId, $transform);
            $cx = $worldPos[0] + $circle->offsetX;
            $cy = $worldPos[1] + $circle->offsetY;

            $t = self::rayCircleIntersect($originX, $originY, $dirX, $dirY, $cx, $cy, $circle->radius);

            if ($t !== null && $t >= 0 && $t < $closestDist) {
                $closestDist = $t;
                $closest = new Raycast2DResult(
                    $entityId,
                    $t,
                    $originX + $dirX * $t,
                    $originY + $dirY * $t,
                );
            }
        }

        return $closest;
    }

    /**
     * @return array{float, float}
     */
    private static function getWorldPos(EntitiesInterface $entities, int $entityId, Transform $transform): array
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
     * Ray-AABB intersection. Returns t (distance along ray) or null.
     */
    private static function rayBoxIntersect(
        float $ox, float $oy, float $dx, float $dy,
        float $minX, float $minY, float $maxX, float $maxY,
    ): ?float {
        if ($dx === 0.0 && $dy === 0.0) {
            return null;
        }

        // For axis-aligned zero direction: check if origin is within slab
        if ($dx === 0.0) {
            if ($ox < $minX || $ox > $maxX) {
                return null;
            }
            $tMinX = -INF;
            $tMaxX = INF;
        } else {
            $tMinX = ($minX - $ox) / $dx;
            $tMaxX = ($maxX - $ox) / $dx;
            if ($tMinX > $tMaxX) { $tmp = $tMinX; $tMinX = $tMaxX; $tMaxX = $tmp; }
        }

        if ($dy === 0.0) {
            if ($oy < $minY || $oy > $maxY) {
                return null;
            }
            $tMinY = -INF;
            $tMaxY = INF;
        } else {
            $tMinY = ($minY - $oy) / $dy;
            $tMaxY = ($maxY - $oy) / $dy;
            if ($tMinY > $tMaxY) { $tmp = $tMinY; $tMinY = $tMaxY; $tMaxY = $tmp; }
        }

        $tEnter = max($tMinX, $tMinY);
        $tExit = min($tMaxX, $tMaxY);

        if ($tEnter > $tExit || $tExit < 0) {
            return null;
        }

        return $tEnter >= 0 ? $tEnter : $tExit;
    }

    /**
     * Ray-Circle intersection. Returns t (distance along ray) or null.
     */
    private static function rayCircleIntersect(
        float $ox, float $oy, float $dx, float $dy,
        float $cx, float $cy, float $r,
    ): ?float {
        $fx = $ox - $cx;
        $fy = $oy - $cy;

        $a = $dx * $dx + $dy * $dy;
        if ($a === 0.0) {
            return null;
        }
        $b = 2.0 * ($fx * $dx + $fy * $dy);
        $c = $fx * $fx + $fy * $fy - $r * $r;

        $discriminant = $b * $b - 4.0 * $a * $c;
        if ($discriminant < 0) {
            return null;
        }

        $sqrtD = sqrt($discriminant);
        $t1 = (-$b - $sqrtD) / (2.0 * $a);
        $t2 = (-$b + $sqrtD) / (2.0 * $a);

        if ($t1 >= 0) {
            return $t1;
        }
        if ($t2 >= 0) {
            return $t2;
        }
        return null;
    }
}
