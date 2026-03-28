<?php

namespace VISU\Tests\Benchmark;

use GL\Math\Vec3;
use VISU\Geo\AABB;
use VISU\Geo\Ray;

class CollisionBench
{
    /** @var array<AABB> */
    private array $aabbs;
    private Ray $ray;
    private AABB $testAABB;

    public function setUp(): void
    {
        $this->aabbs = [];
        for ($i = 0; $i < 1000; $i++) {
            $x = ($i % 32) * 3.0;
            $z = (int)($i / 32) * 3.0;
            $this->aabbs[] = new AABB(
                new Vec3($x - 1, -1, $z - 1),
                new Vec3($x + 1, 1, $z + 1),
            );
        }

        $this->ray = new Ray(
            new Vec3(0, 0.5, 0),
            new Vec3(1, 0.1, 0.5),
        );

        $this->testAABB = new AABB(
            new Vec3(-1, -1, -1),
            new Vec3(1, 1, 1),
        );
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchAABBvsAABB1000(): void
    {
        foreach ($this->aabbs as $aabb) {
            $this->testAABB->intersects($aabb);
        }
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchRayVsAABB1000(): void
    {
        foreach ($this->aabbs as $aabb) {
            $aabb->intersectRayDistance($this->ray);
        }
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(10000)
     * @Iterations(5)
     */
    public function benchAABBContainsPoint(): void
    {
        $point = new Vec3(0.5, 0.5, 0.5);
        for ($i = 0; $i < 100; $i++) {
            $this->testAABB->contains($point);
        }
    }
}
