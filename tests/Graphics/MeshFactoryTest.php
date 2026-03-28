<?php

namespace VISU\Tests\Graphics;

use PHPUnit\Framework\TestCase;
use GL\Math\Vec4;
use VISU\Graphics\Material;
use VISU\Graphics\Mesh3D;
use VISU\Graphics\MeshFactory;
use VISU\Graphics\MeshGeometry;
use VISU\Graphics\ModelCollection;
use VISU\Graphics\PrimitiveShape;

class MeshFactoryTest extends TestCase
{
    // -----------------------------------------------------------------------
    //  PrimitiveShape enum
    // -----------------------------------------------------------------------

    public function testPrimitiveShapeModelId(): void
    {
        $this->assertEquals('__primitive_cube', PrimitiveShape::cube->modelId());
        $this->assertEquals('__primitive_sphere', PrimitiveShape::sphere->modelId());
        $this->assertEquals('__primitive_torus', PrimitiveShape::torus->modelId());
    }

    public function testPrimitiveShapeFromString(): void
    {
        $this->assertSame(PrimitiveShape::cube, PrimitiveShape::from('cube'));
        $this->assertSame(PrimitiveShape::capsule, PrimitiveShape::from('capsule'));
    }

    public function testAllPrimitiveShapesHaveUniqueModelIds(): void
    {
        $ids = [];
        foreach (PrimitiveShape::cases() as $shape) {
            $ids[] = $shape->modelId();
        }
        $this->assertCount(count(array_unique($ids)), $ids);
    }

    // -----------------------------------------------------------------------
    //  Geometry generation (no GL required)
    // -----------------------------------------------------------------------

    /**
     * @dataProvider primitiveGeneratorProvider
     */
    public function testGeneratedGeometryHasValidVertexStride(MeshGeometry $geometry, string $name): void
    {
        $this->assertEquals(0, $geometry->vertices->size() % Mesh3D::STRIDE, "{$name}: vertex buffer size must be multiple of stride (12)");
    }

    /**
     * @dataProvider primitiveGeneratorProvider
     */
    public function testGeneratedGeometryHasValidIndices(MeshGeometry $geometry, string $name): void
    {
        $this->assertEquals(0, $geometry->indices->size() % 3, "{$name}: index count must be multiple of 3");
        $this->assertTrue($geometry->validate(), "{$name}: indices must be within vertex bounds");
    }

    /**
     * @dataProvider primitiveGeneratorProvider
     */
    public function testGeneratedGeometryHasVerticesAndIndices(MeshGeometry $geometry, string $name): void
    {
        $this->assertGreaterThan(0, $geometry->getVertexCount(), "{$name}: must have vertices");
        $this->assertGreaterThan(0, $geometry->getIndexCount(), "{$name}: must have indices");
        $this->assertGreaterThan(0, $geometry->getTriangleCount(), "{$name}: must have triangles");
    }

    /**
     * @dataProvider primitiveGeneratorProvider
     */
    public function testGeneratedGeometryAABBIsValid(MeshGeometry $geometry, string $name): void
    {
        $this->assertLessThanOrEqual($geometry->aabb->max->x, $geometry->aabb->min->x, "{$name}: AABB min.x <= max.x");
        $this->assertLessThanOrEqual($geometry->aabb->max->y, $geometry->aabb->min->y, "{$name}: AABB min.y <= max.y");
        $this->assertLessThanOrEqual($geometry->aabb->max->z, $geometry->aabb->min->z, "{$name}: AABB min.z <= max.z");
    }

    /**
     * @dataProvider primitiveGeneratorProvider
     */
    public function testNormalsAreNormalized(MeshGeometry $geometry, string $name): void
    {
        $vertexCount = $geometry->getVertexCount();
        for ($i = 0; $i < $vertexCount; $i++) {
            $offset = $i * Mesh3D::STRIDE;
            $nx = $geometry->vertices[$offset + 3];
            $ny = $geometry->vertices[$offset + 4];
            $nz = $geometry->vertices[$offset + 5];
            $length = sqrt($nx * $nx + $ny * $ny + $nz * $nz);
            $this->assertEqualsWithDelta(1.0, $length, 0.01, "{$name}: normal at vertex {$i} not normalized (length={$length})");
        }
    }

    // -----------------------------------------------------------------------
    //  Specific shape tests
    // -----------------------------------------------------------------------

    public function testCubeGeometry(): void
    {
        $geo = MeshFactory::generateCube(1.0);
        // 6 faces * 4 vertices = 24 vertices
        $this->assertEquals(24, $geo->getVertexCount());
        // 6 faces * 2 triangles = 12 triangles = 36 indices
        $this->assertEquals(36, $geo->getIndexCount());
    }

    public function testCubeCustomSize(): void
    {
        $geo = MeshFactory::generateCube(2.0);
        $this->assertEqualsWithDelta(1.0, $geo->aabb->max->x, 0.001);
        $this->assertEqualsWithDelta(-1.0, $geo->aabb->min->x, 0.001);
    }

    public function testSphereGeometry(): void
    {
        $geo = MeshFactory::generateSphere(0.5, 16, 8);
        // (8+1) * (16+1) = 153 vertices
        $this->assertEquals(153, $geo->getVertexCount());
        // 8 * 16 * 2 = 256 triangles
        $this->assertEquals(256, $geo->getTriangleCount());
    }

    public function testSphereCustomRadius(): void
    {
        $geo = MeshFactory::generateSphere(1.0);
        $this->assertEqualsWithDelta(1.0, $geo->aabb->max->x, 0.001);
        $this->assertEqualsWithDelta(-1.0, $geo->aabb->min->x, 0.001);
    }

    public function testPlaneGeometry(): void
    {
        $geo = MeshFactory::generatePlane(1.0);
        $this->assertEquals(4, $geo->getVertexCount());
        $this->assertEquals(2, $geo->getTriangleCount());
        // Y should be 0
        $this->assertEqualsWithDelta(0.0, $geo->aabb->min->y, 0.001);
        $this->assertEqualsWithDelta(0.0, $geo->aabb->max->y, 0.001);
    }

    public function testQuadGeometry(): void
    {
        $geo = MeshFactory::generateQuad(1.0);
        $this->assertEquals(4, $geo->getVertexCount());
        $this->assertEquals(2, $geo->getTriangleCount());
        // Z should be 0
        $this->assertEqualsWithDelta(0.0, $geo->aabb->min->z, 0.001);
        $this->assertEqualsWithDelta(0.0, $geo->aabb->max->z, 0.001);
    }

    public function testCylinderGeometry(): void
    {
        $geo = MeshFactory::generateCylinder(0.5, 2.0, 16);
        $this->assertEqualsWithDelta(1.0, $geo->aabb->max->y, 0.001);
        $this->assertEqualsWithDelta(-1.0, $geo->aabb->min->y, 0.001);
        $this->assertTrue($geo->validate());
    }

    public function testConeGeometry(): void
    {
        $geo = MeshFactory::generateCone(0.5, 1.0, 16);
        $this->assertEqualsWithDelta(0.5, $geo->aabb->max->y, 0.001);
        $this->assertEqualsWithDelta(-0.5, $geo->aabb->min->y, 0.001);
        $this->assertTrue($geo->validate());
    }

    public function testCapsuleGeometry(): void
    {
        $geo = MeshFactory::generateCapsule(0.25, 1.0, 16, 4);
        $this->assertEqualsWithDelta(0.5, $geo->aabb->max->y, 0.001);
        $this->assertEqualsWithDelta(-0.5, $geo->aabb->min->y, 0.001);
        $this->assertTrue($geo->validate());
    }

    public function testCapsuleMinimumHeight(): void
    {
        // Height smaller than diameter — cylinder section should be 0
        $geo = MeshFactory::generateCapsule(0.5, 0.5, 16, 4);
        $this->assertTrue($geo->validate());
    }

    public function testTorusGeometry(): void
    {
        $geo = MeshFactory::generateTorus(1.0, 0.3, 16, 8);
        $outerR = 1.3;
        $this->assertEqualsWithDelta($outerR, $geo->aabb->max->x, 0.001);
        $this->assertEqualsWithDelta(-$outerR, $geo->aabb->min->x, 0.001);
        $this->assertEqualsWithDelta(0.3, $geo->aabb->max->y, 0.001);
        $this->assertTrue($geo->validate());
    }

    // -----------------------------------------------------------------------
    //  MeshGeometry helper methods
    // -----------------------------------------------------------------------

    public function testMeshGeometryTriangleCount(): void
    {
        $geo = MeshFactory::generateCube();
        $this->assertEquals($geo->getIndexCount() / 3, $geo->getTriangleCount());
    }

    // -----------------------------------------------------------------------
    //  Data providers
    // -----------------------------------------------------------------------

    /**
     * @return array<string, array{MeshGeometry, string}>
     */
    public static function primitiveGeneratorProvider(): array
    {
        return [
            'cube' => [MeshFactory::generateCube(), 'cube'],
            'sphere' => [MeshFactory::generateSphere(0.5, 16, 8), 'sphere'],
            'plane' => [MeshFactory::generatePlane(), 'plane'],
            'quad' => [MeshFactory::generateQuad(), 'quad'],
            'cylinder' => [MeshFactory::generateCylinder(0.5, 1.0, 16), 'cylinder'],
            'cone' => [MeshFactory::generateCone(0.5, 1.0, 16), 'cone'],
            'capsule' => [MeshFactory::generateCapsule(0.25, 1.0, 16, 4), 'capsule'],
            'torus' => [MeshFactory::generateTorus(0.35, 0.15, 16, 8), 'torus'],
        ];
    }
}
