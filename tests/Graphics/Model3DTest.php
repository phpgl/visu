<?php

namespace Tests\Graphics;

use GL\Math\Vec3;
use PHPUnit\Framework\TestCase;
use VISU\Geo\AABB;
use VISU\Graphics\Model3D;

class Model3DTest extends TestCase
{
    public function testConstruction(): void
    {
        $model = new Model3D('test_model');
        $this->assertEquals('test_model', $model->name);
        $this->assertEmpty($model->meshes);
        $this->assertInstanceOf(AABB::class, $model->aabb);
    }

    public function testRecalculateAABBEmpty(): void
    {
        $model = new Model3D('empty');
        $model->recalculateAABB();
        $this->assertEquals(0.0, $model->aabb->min->x);
        $this->assertEquals(0.0, $model->aabb->max->x);
    }
}
