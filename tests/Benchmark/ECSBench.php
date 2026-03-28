<?php

namespace VISU\Tests\Benchmark;

use VISU\ECS\EntityRegistry;
use VISU\Geo\Transform;

class ECSBench
{
    private EntityRegistry $registry;

    public function setUp(): void
    {
        $this->registry = new \VISU\ECS\EntityRegistry();
        $this->registry->registerComponent(Transform::class);

        // pre-populate with 10k entities
        for ($i = 0; $i < 10000; $i++) {
            $entity = $this->registry->create();
            $this->registry->attach($entity, new Transform());
        }
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(100)
     * @Iterations(5)
     */
    public function benchCreateEntity(): void
    {
        for ($i = 0; $i < 1000; $i++) {
            $this->registry->create();
        }
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(100)
     * @Iterations(5)
     */
    public function benchIterateComponents10k(): void
    {
        foreach ($this->registry->view(Transform::class) as $entity => $transform) {
            $transform->markDirty();
        }
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(100)
     * @Iterations(5)
     */
    public function benchAttachDetach(): void
    {
        $entity = $this->registry->create();
        for ($i = 0; $i < 1000; $i++) {
            $this->registry->attach($entity, new Transform());
            $this->registry->detach($entity, Transform::class);
        }
    }
}
