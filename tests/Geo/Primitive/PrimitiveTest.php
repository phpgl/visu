<?php

namespace VISU\Tests\Geo\Primitive;

use GL\Buffer\FloatBuffer;
use GL\Math\Vec3;
use VISU\Geo\Primitive\Cone;
use VISU\Geo\Primitive\Cube;
use VISU\Geo\Primitive\Cylinder;
use VISU\Geo\Primitive\Plane;
use VISU\Geo\Primitive\PrimitiveBuilder;
use VISU\Geo\Primitive\PrimitiveException;
use VISU\Geo\Primitive\Sphere;
use VISU\Geo\Primitive\VertexLayout;

class PrimitiveTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @return array<string, array{0: callable(VertexLayout, FloatBuffer): void}>
     */
    public static function primitiveProvider() : array
    {
        return [
            'sphere' => [fn (VertexLayout $l, FloatBuffer $t) => Sphere::make($l, $t, 0.5, 16, 8)],
            'cube' => [fn (VertexLayout $l, FloatBuffer $t) => Cube::make($l, $t, 2.0, 1.0, 3.0)],
            'plane' => [fn (VertexLayout $l, FloatBuffer $t) => Plane::make($l, $t, 4.0, 4.0, 3)],
            'cylinder' => [fn (VertexLayout $l, FloatBuffer $t) => Cylinder::make($l, $t, 1.0, 2.0, 12)],
            'cone' => [fn (VertexLayout $l, FloatBuffer $t) => Cone::make($l, $t, 1.0, 2.0, 12)],
        ];
    }

    /**
     * @dataProvider primitiveProvider
     */
    public function testProducesWholeTrianglesForPositionNormal(callable $make)
    {
        $layout = VertexLayout::with([VertexLayout::position, VertexLayout::normal]);
        $buffer = new FloatBuffer();
        $make($layout, $buffer);

        $this->assertGreaterThan(0, $buffer->size());
        $this->assertSame(0, $buffer->size() % $layout->stride(), 'buffer holds whole vertices');
        $this->assertSame(0, $buffer->size() % (3 * $layout->stride()), 'buffer holds whole triangles');
    }

    /**
     * @dataProvider primitiveProvider
     */
    public function testProducesWholeTrianglesForPositionNormalUv(callable $make)
    {
        $layout = VertexLayout::with([VertexLayout::position, VertexLayout::normal, VertexLayout::uv]);
        $buffer = new FloatBuffer();
        $make($layout, $buffer);

        $this->assertSame(8, $layout->stride());
        $this->assertGreaterThan(0, $buffer->size());
        $this->assertSame(0, $buffer->size() % (3 * $layout->stride()));
    }

    public function testAppendsIntoExistingBuffer()
    {
        $layout = VertexLayout::with([VertexLayout::position, VertexLayout::normal]);
        $buffer = new FloatBuffer();

        Cube::standardCube($layout, $buffer);
        $afterCube = $buffer->size();

        Sphere::unitSphere($layout, $buffer);

        $this->assertGreaterThan($afterCube, $buffer->size());
    }

    public function testSphereNormalsAreUnitAndPositionsOnRadius()
    {
        $radius = 0.5;
        $layout = VertexLayout::with([VertexLayout::position, VertexLayout::normal]);
        $buffer = new FloatBuffer();
        Sphere::make($layout, $buffer, $radius, 16, 8);

        for ($i = 0; $i < $buffer->size(); $i += 6) {
            $pos = new Vec3($buffer[$i], $buffer[$i + 1], $buffer[$i + 2]);
            $normal = new Vec3($buffer[$i + 3], $buffer[$i + 4], $buffer[$i + 5]);

            $this->assertEqualsWithDelta(1.0, $normal->length(), 1e-4);
            $this->assertEqualsWithDelta($radius, $pos->length(), 1e-4);
        }
    }

    public function testSphereHasNoDegenerateOrInwardTriangles()
    {
        $layout = VertexLayout::with([VertexLayout::position, VertexLayout::normal]);
        $buffer = new FloatBuffer();
        Sphere::make($layout, $buffer, 0.5, 32, 16);

        $stride = 6;
        for ($i = 0; $i < $buffer->size(); $i += 3 * $stride) {
            $p0 = new Vec3($buffer[$i], $buffer[$i + 1], $buffer[$i + 2]);
            $p1 = new Vec3($buffer[$i + $stride], $buffer[$i + $stride + 1], $buffer[$i + $stride + 2]);
            $p2 = new Vec3($buffer[$i + 2 * $stride], $buffer[$i + 2 * $stride + 1], $buffer[$i + 2 * $stride + 2]);

            $faceNormal = Vec3::cross($p1 - $p0, $p2 - $p0);

            // no degenerate (zero area) triangles, especially at the poles
            $this->assertGreaterThan(1e-9, $faceNormal->length(), 'triangle is degenerate');

            // winding matches the stored normals, i.e. the face points outward
            $averageNormal = new Vec3(
                $buffer[$i + 3] + $buffer[$i + $stride + 3] + $buffer[$i + 2 * $stride + 3],
                $buffer[$i + 4] + $buffer[$i + $stride + 4] + $buffer[$i + 2 * $stride + 4],
                $buffer[$i + 5] + $buffer[$i + $stride + 5] + $buffer[$i + 2 * $stride + 5]
            );
            $this->assertGreaterThan(0.0, Vec3::dot($faceNormal, $averageNormal), 'triangle faces inward');
        }
    }

    public function testBuilderThrowsWhenAttributeMissing()
    {
        $this->expectException(PrimitiveException::class);

        $layout = VertexLayout::with([VertexLayout::position, VertexLayout::uv]);
        $builder = new PrimitiveBuilder($layout, new FloatBuffer());

        // uv is requested but not provided
        $builder->addVertex(new Vec3(0.0, 0.0, 0.0));
    }
}
