<?php

namespace Tests\Graphics;

use PHPUnit\Framework\TestCase;
use VISU\Exception\VISUException;
use VISU\Graphics\Model3D;
use VISU\Graphics\ModelCollection;

class ModelCollectionTest extends TestCase
{
    public function testAddAndGet(): void
    {
        $collection = new ModelCollection();
        $model = new Model3D('cube');
        $collection->add($model);

        $this->assertTrue($collection->has('cube'));
        $this->assertSame($model, $collection->get('cube'));
    }

    public function testHasReturnsFalseForMissing(): void
    {
        $collection = new ModelCollection();
        $this->assertFalse($collection->has('nonexistent'));
    }

    public function testGetThrowsForMissing(): void
    {
        $collection = new ModelCollection();
        $this->expectException(VISUException::class);
        $collection->get('nonexistent');
    }

    public function testAddDuplicateThrows(): void
    {
        $collection = new ModelCollection();
        $collection->add(new Model3D('cube'));
        $this->expectException(VISUException::class);
        $collection->add(new Model3D('cube'));
    }
}
