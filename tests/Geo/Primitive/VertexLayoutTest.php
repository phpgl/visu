<?php

namespace VISU\Tests\Geo\Primitive;

use VISU\Geo\Primitive\VertexAttribute;
use VISU\Geo\Primitive\VertexLayout;

class VertexLayoutTest extends \PHPUnit\Framework\TestCase
{
    public function testStride()
    {
        $layout = VertexLayout::with([
            VertexLayout::position,
            VertexLayout::normal,
            VertexLayout::uv,
        ]);

        $this->assertSame(8, $layout->stride());
    }

    public function testPositionOnlyStride()
    {
        $layout = VertexLayout::with([VertexLayout::position]);
        $this->assertSame(3, $layout->stride());
    }

    public function testOffsets()
    {
        $layout = VertexLayout::with([
            VertexLayout::position,
            VertexLayout::normal,
            VertexLayout::uv,
        ]);

        $this->assertSame(0, $layout->offsetOf(VertexAttribute::position));
        $this->assertSame(3, $layout->offsetOf(VertexAttribute::normal));
        $this->assertSame(6, $layout->offsetOf(VertexAttribute::uv));
    }

    public function testHasAndMissingOffset()
    {
        $layout = VertexLayout::with([VertexLayout::position, VertexLayout::normal]);

        $this->assertTrue($layout->has(VertexAttribute::normal));
        $this->assertFalse($layout->has(VertexAttribute::uv));
        $this->assertSame(-1, $layout->offsetOf(VertexAttribute::uv));
    }

    public function testDuplicatesAreRemoved()
    {
        $layout = VertexLayout::with([
            VertexLayout::position,
            VertexLayout::position,
            VertexLayout::normal,
        ]);

        $this->assertCount(2, $layout->attributes());
        $this->assertSame(6, $layout->stride());
    }
}
