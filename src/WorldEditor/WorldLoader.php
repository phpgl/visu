<?php

namespace VISU\WorldEditor;

use GL\Math\GLM;
use GL\Math\Vec3;
use GL\Math\Quat;
use VISU\ECS\EntityRegistry;
use VISU\Geo\Transform;

class WorldLoader
{
    /**
     * Load a WorldFile into an EntityRegistry.
     *
     * Only entity layers are processed. Each entity gets a Transform component
     * with position and scale populated from the world data.
     */
    public function load(WorldFile $world, EntityRegistry $registry): void
    {
        $registry->registerComponent(Transform::class);

        foreach ($world->getLayers() as $layer) {
            if (($layer['type'] ?? '') !== 'entity') {
                continue;
            }

            foreach (($layer['entities'] ?? []) as $entityData) {
                $entity = $registry->create();

                $transform = new Transform();

                $x = (float)($entityData['position']['x'] ?? 0);
                $y = (float)($entityData['position']['y'] ?? 0);
                $transform->position = new Vec3($x, $y, 0.0);

                $sx = (float)($entityData['scale']['x'] ?? 1.0);
                $sy = (float)($entityData['scale']['y'] ?? 1.0);
                $transform->scale = new Vec3($sx, $sy, 1.0);

                $rotation = (float)($entityData['rotation'] ?? 0.0);
                if ($rotation !== 0.0) {
                    $q = new Quat();
                    $q->rotate(GLM::radians($rotation), new Vec3(0.0, 0.0, 1.0));
                    $transform->orientation = $q;
                }

                $registry->attach($entity, $transform);
            }
        }
    }
}
