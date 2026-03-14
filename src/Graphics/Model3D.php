<?php

namespace VISU\Graphics;

use GL\Math\Vec3;
use VISU\Geo\AABB;

class Model3D
{
    public string $name;

    /**
     * @var array<Mesh3D>
     */
    public array $meshes = [];

    public AABB $aabb;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->aabb = new AABB(new Vec3(0, 0, 0), new Vec3(0, 0, 0));
    }

    /**
     * Adds a mesh to the model
     */
    public function addMesh(Mesh3D $mesh): void
    {
        $this->meshes[] = $mesh;
    }

    /**
     * Recalculates the AABB from all meshes
     */
    public function recalculateAABB(): void
    {
        if (empty($this->meshes)) {
            $this->aabb = new AABB(new Vec3(0, 0, 0), new Vec3(0, 0, 0));
            return;
        }

        $this->aabb = new AABB(
            new Vec3($this->meshes[0]->aabb->min->x, $this->meshes[0]->aabb->min->y, $this->meshes[0]->aabb->min->z),
            new Vec3($this->meshes[0]->aabb->max->x, $this->meshes[0]->aabb->max->y, $this->meshes[0]->aabb->max->z),
        );

        for ($i = 1; $i < count($this->meshes); $i++) {
            $this->aabb->extend($this->meshes[$i]->aabb);
        }
    }
}
