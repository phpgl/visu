<?php

namespace VISU\Graphics;

use GL\Buffer\FloatBuffer;
use GL\Buffer\UIntBuffer;
use GL\Math\Vec3;
use GL\Math\Vec4;
use VISU\Geo\AABB;

class MeshFactory
{
    private ?Material $defaultMaterial = null;

    public function __construct(
        private readonly GLState $gl,
    ) {
    }

    /**
     * Returns a default gray PBR material for primitives
     */
    public function getDefaultMaterial(): Material
    {
        if ($this->defaultMaterial === null) {
            $this->defaultMaterial = new Material(
                name: 'primitive_default',
                albedoColor: new Vec4(0.8, 0.8, 0.8, 1.0),
                metallic: 0.0,
                roughness: 0.5,
            );
        }

        return $this->defaultMaterial;
    }

    /**
     * Creates a Model3D for the given primitive shape (requires GL context)
     */
    public function createPrimitive(PrimitiveShape $shape, ?Material $material = null): Model3D
    {
        $material ??= $this->getDefaultMaterial();

        return match ($shape) {
            PrimitiveShape::cube => $this->createCube($material),
            PrimitiveShape::sphere => $this->createSphere($material),
            PrimitiveShape::plane => $this->createPlane($material),
            PrimitiveShape::cylinder => $this->createCylinder($material),
            PrimitiveShape::capsule => $this->createCapsule($material),
            PrimitiveShape::quad => $this->createQuad($material),
            PrimitiveShape::cone => $this->createCone($material),
            PrimitiveShape::torus => $this->createTorus($material),
        };
    }

    /**
     * Creates all primitives and registers them in the given ModelCollection
     */
    public function registerAll(ModelCollection $collection, ?Material $material = null): void
    {
        foreach (PrimitiveShape::cases() as $shape) {
            if (!$collection->has($shape->modelId())) {
                $model = $this->createPrimitive($shape, $material);
                $model->name = $shape->modelId();
                $collection->add($model);
            }
        }
    }

    // -----------------------------------------------------------------------
    //  Create methods (geometry + GPU upload -> Model3D)
    // -----------------------------------------------------------------------

    public function createCube(Material $material, float $size = 1.0): Model3D
    {
        return $this->uploadGeometry('cube', $material, self::generateCube($size));
    }

    public function createSphere(Material $material, float $radius = 0.5, int $segments = 32, int $rings = 16): Model3D
    {
        return $this->uploadGeometry('sphere', $material, self::generateSphere($radius, $segments, $rings));
    }

    public function createPlane(Material $material, float $size = 1.0): Model3D
    {
        return $this->uploadGeometry('plane', $material, self::generatePlane($size));
    }

    public function createQuad(Material $material, float $size = 1.0): Model3D
    {
        return $this->uploadGeometry('quad', $material, self::generateQuad($size));
    }

    public function createCylinder(Material $material, float $radius = 0.5, float $height = 1.0, int $segments = 32): Model3D
    {
        return $this->uploadGeometry('cylinder', $material, self::generateCylinder($radius, $height, $segments));
    }

    public function createCone(Material $material, float $radius = 0.5, float $height = 1.0, int $segments = 32): Model3D
    {
        return $this->uploadGeometry('cone', $material, self::generateCone($radius, $height, $segments));
    }

    public function createCapsule(Material $material, float $radius = 0.25, float $height = 1.0, int $segments = 32, int $rings = 8): Model3D
    {
        return $this->uploadGeometry('capsule', $material, self::generateCapsule($radius, $height, $segments, $rings));
    }

    public function createTorus(Material $material, float $majorRadius = 0.35, float $minorRadius = 0.15, int $majorSegments = 32, int $minorSegments = 16): Model3D
    {
        return $this->uploadGeometry('torus', $material, self::generateTorus($majorRadius, $minorRadius, $majorSegments, $minorSegments));
    }

    // -----------------------------------------------------------------------
    //  Static geometry generators (no GL required)
    // -----------------------------------------------------------------------

    /**
     * Unit cube centered at origin
     */
    public static function generateCube(float $size = 1.0): MeshGeometry
    {
        $h = $size * 0.5;
        $vertices = new FloatBuffer();
        $indices = new UIntBuffer();

        // 6 faces: [nx, ny, nz, tx, ty, tz, [[px, py, pz, u, v], ...]]
        $faces = [
            [0, 0, 1, 1, 0, 0, [[-$h, -$h, $h, 0, 0], [$h, -$h, $h, 1, 0], [$h, $h, $h, 1, 1], [-$h, $h, $h, 0, 1]]],
            [0, 0, -1, -1, 0, 0, [[$h, -$h, -$h, 0, 0], [-$h, -$h, -$h, 1, 0], [-$h, $h, -$h, 1, 1], [$h, $h, -$h, 0, 1]]],
            [1, 0, 0, 0, 0, 1, [[$h, -$h, $h, 0, 0], [$h, -$h, -$h, 1, 0], [$h, $h, -$h, 1, 1], [$h, $h, $h, 0, 1]]],
            [-1, 0, 0, 0, 0, -1, [[-$h, -$h, -$h, 0, 0], [-$h, -$h, $h, 1, 0], [-$h, $h, $h, 1, 1], [-$h, $h, -$h, 0, 1]]],
            [0, 1, 0, 1, 0, 0, [[-$h, $h, $h, 0, 0], [$h, $h, $h, 1, 0], [$h, $h, -$h, 1, 1], [-$h, $h, -$h, 0, 1]]],
            [0, -1, 0, 1, 0, 0, [[-$h, -$h, -$h, 0, 0], [$h, -$h, -$h, 1, 0], [$h, -$h, $h, 1, 1], [-$h, -$h, $h, 0, 1]]],
        ];

        $idx = 0;
        foreach ($faces as [$nx, $ny, $nz, $tx, $ty, $tz, $verts]) {
            foreach ($verts as [$px, $py, $pz, $u, $v]) {
                self::pushVertex($vertices, $px, $py, $pz, $nx, $ny, $nz, $u, $v, $tx, $ty, $tz, 1.0);
            }
            $indices->push($idx);
            $indices->push($idx + 1);
            $indices->push($idx + 2);
            $indices->push($idx);
            $indices->push($idx + 2);
            $indices->push($idx + 3);
            $idx += 4;
        }

        return new MeshGeometry($vertices, $indices, new AABB(new Vec3(-$h, -$h, -$h), new Vec3($h, $h, $h)));
    }

    /**
     * UV sphere centered at origin
     */
    public static function generateSphere(float $radius = 0.5, int $segments = 32, int $rings = 16): MeshGeometry
    {
        $vertices = new FloatBuffer();
        $indices = new UIntBuffer();

        for ($j = 0; $j <= $rings; $j++) {
            $theta = $j * M_PI / $rings;
            $sinTheta = sin($theta);
            $cosTheta = cos($theta);

            for ($i = 0; $i <= $segments; $i++) {
                $phi = $i * 2.0 * M_PI / $segments;
                $sinPhi = sin($phi);
                $cosPhi = cos($phi);

                $nx = $sinTheta * $cosPhi;
                $ny = $cosTheta;
                $nz = $sinTheta * $sinPhi;

                self::pushVertex(
                    $vertices,
                    $nx * $radius, $ny * $radius, $nz * $radius,
                    $nx, $ny, $nz,
                    $i / $segments, $j / $rings,
                    -$sinPhi, 0.0, $cosPhi, 1.0
                );
            }
        }

        for ($j = 0; $j < $rings; $j++) {
            for ($i = 0; $i < $segments; $i++) {
                $a = $j * ($segments + 1) + $i;
                $b = $a + $segments + 1;
                $indices->push($a);
                $indices->push($b);
                $indices->push($a + 1);
                $indices->push($b);
                $indices->push($b + 1);
                $indices->push($a + 1);
            }
        }

        return new MeshGeometry(
            $vertices, $indices,
            new AABB(new Vec3(-$radius, -$radius, -$radius), new Vec3($radius, $radius, $radius))
        );
    }

    /**
     * Horizontal plane on Y=0 (XZ plane)
     */
    public static function generatePlane(float $size = 1.0): MeshGeometry
    {
        $h = $size * 0.5;
        $vertices = new FloatBuffer();
        $indices = new UIntBuffer();

        self::pushVertex($vertices, -$h, 0, -$h, 0, 1, 0, 0, 0, 1, 0, 0, 1.0);
        self::pushVertex($vertices, $h, 0, -$h, 0, 1, 0, 1, 0, 1, 0, 0, 1.0);
        self::pushVertex($vertices, $h, 0, $h, 0, 1, 0, 1, 1, 1, 0, 0, 1.0);
        self::pushVertex($vertices, -$h, 0, $h, 0, 1, 0, 0, 1, 1, 0, 0, 1.0);

        $indices->push(0); $indices->push(1); $indices->push(2);
        $indices->push(0); $indices->push(2); $indices->push(3);

        return new MeshGeometry($vertices, $indices, new AABB(new Vec3(-$h, 0, -$h), new Vec3($h, 0, $h)));
    }

    /**
     * Vertical quad facing +Z (XY plane)
     */
    public static function generateQuad(float $size = 1.0): MeshGeometry
    {
        $h = $size * 0.5;
        $vertices = new FloatBuffer();
        $indices = new UIntBuffer();

        self::pushVertex($vertices, -$h, -$h, 0, 0, 0, 1, 0, 0, 1, 0, 0, 1.0);
        self::pushVertex($vertices, $h, -$h, 0, 0, 0, 1, 1, 0, 1, 0, 0, 1.0);
        self::pushVertex($vertices, $h, $h, 0, 0, 0, 1, 1, 1, 1, 0, 0, 1.0);
        self::pushVertex($vertices, -$h, $h, 0, 0, 0, 1, 0, 1, 1, 0, 0, 1.0);

        $indices->push(0); $indices->push(1); $indices->push(2);
        $indices->push(0); $indices->push(2); $indices->push(3);

        return new MeshGeometry($vertices, $indices, new AABB(new Vec3(-$h, -$h, 0), new Vec3($h, $h, 0)));
    }

    /**
     * Cylinder along Y axis, centered at origin
     */
    public static function generateCylinder(float $radius = 0.5, float $height = 1.0, int $segments = 32): MeshGeometry
    {
        $hh = $height * 0.5;
        $vertices = new FloatBuffer();
        $indices = new UIntBuffer();

        // Side
        for ($i = 0; $i <= $segments; $i++) {
            $angle = $i * 2.0 * M_PI / $segments;
            $cos = cos($angle);
            $sin = sin($angle);
            $u = $i / $segments;
            self::pushVertex($vertices, $cos * $radius, -$hh, $sin * $radius, $cos, 0, $sin, $u, 0, -$sin, 0, $cos, 1.0);
            self::pushVertex($vertices, $cos * $radius, $hh, $sin * $radius, $cos, 0, $sin, $u, 1, -$sin, 0, $cos, 1.0);
        }

        for ($i = 0; $i < $segments; $i++) {
            $b = $i * 2;
            $indices->push($b); $indices->push($b + 2); $indices->push($b + 1);
            $indices->push($b + 1); $indices->push($b + 2); $indices->push($b + 3);
        }

        $idx = ($segments + 1) * 2;

        // Top cap
        $topCenter = $idx;
        self::pushVertex($vertices, 0, $hh, 0, 0, 1, 0, 0.5, 0.5, 1, 0, 0, 1.0);
        $idx++;
        for ($i = 0; $i <= $segments; $i++) {
            $angle = $i * 2.0 * M_PI / $segments;
            $cos = cos($angle);
            $sin = sin($angle);
            self::pushVertex($vertices, $cos * $radius, $hh, $sin * $radius, 0, 1, 0, $cos * 0.5 + 0.5, $sin * 0.5 + 0.5, 1, 0, 0, 1.0);
            $idx++;
        }
        for ($i = 0; $i < $segments; $i++) {
            $indices->push($topCenter); $indices->push($topCenter + 1 + $i); $indices->push($topCenter + 2 + $i);
        }

        // Bottom cap
        $botCenter = $idx;
        self::pushVertex($vertices, 0, -$hh, 0, 0, -1, 0, 0.5, 0.5, 1, 0, 0, 1.0);
        $idx++;
        for ($i = 0; $i <= $segments; $i++) {
            $angle = $i * 2.0 * M_PI / $segments;
            $cos = cos($angle);
            $sin = sin($angle);
            self::pushVertex($vertices, $cos * $radius, -$hh, $sin * $radius, 0, -1, 0, $cos * 0.5 + 0.5, $sin * 0.5 + 0.5, 1, 0, 0, 1.0);
            $idx++;
        }
        for ($i = 0; $i < $segments; $i++) {
            $indices->push($botCenter); $indices->push($botCenter + 2 + $i); $indices->push($botCenter + 1 + $i);
        }

        return new MeshGeometry(
            $vertices, $indices,
            new AABB(new Vec3(-$radius, -$hh, -$radius), new Vec3($radius, $hh, $radius))
        );
    }

    /**
     * Cone along Y axis, base at -height/2, tip at +height/2
     */
    public static function generateCone(float $radius = 0.5, float $height = 1.0, int $segments = 32): MeshGeometry
    {
        $hh = $height * 0.5;
        $vertices = new FloatBuffer();
        $indices = new UIntBuffer();

        $slope = $radius / $height;
        $nLen = sqrt(1.0 + $slope * $slope);

        // Side
        for ($i = 0; $i <= $segments; $i++) {
            $angle = $i * 2.0 * M_PI / $segments;
            $cos = cos($angle);
            $sin = sin($angle);
            $u = $i / $segments;

            $nx = $cos / $nLen;
            $ny = $slope / $nLen;
            $nz = $sin / $nLen;

            self::pushVertex($vertices, $cos * $radius, -$hh, $sin * $radius, $nx, $ny, $nz, $u, 0, -$sin, 0, $cos, 1.0);
            self::pushVertex($vertices, 0, $hh, 0, $nx, $ny, $nz, $u, 1, -$sin, 0, $cos, 1.0);
        }

        for ($i = 0; $i < $segments; $i++) {
            $b = $i * 2;
            $indices->push($b); $indices->push($b + 2); $indices->push($b + 1);
        }

        $idx = ($segments + 1) * 2;

        // Bottom cap
        $botCenter = $idx;
        self::pushVertex($vertices, 0, -$hh, 0, 0, -1, 0, 0.5, 0.5, 1, 0, 0, 1.0);
        $idx++;
        for ($i = 0; $i <= $segments; $i++) {
            $angle = $i * 2.0 * M_PI / $segments;
            $cos = cos($angle);
            $sin = sin($angle);
            self::pushVertex($vertices, $cos * $radius, -$hh, $sin * $radius, 0, -1, 0, $cos * 0.5 + 0.5, $sin * 0.5 + 0.5, 1, 0, 0, 1.0);
            $idx++;
        }
        for ($i = 0; $i < $segments; $i++) {
            $indices->push($botCenter); $indices->push($botCenter + 2 + $i); $indices->push($botCenter + 1 + $i);
        }

        return new MeshGeometry(
            $vertices, $indices,
            new AABB(new Vec3(-$radius, -$hh, -$radius), new Vec3($radius, $hh, $radius))
        );
    }

    /**
     * Capsule along Y axis (cylinder + two hemispheres)
     */
    public static function generateCapsule(float $radius = 0.25, float $height = 1.0, int $segments = 32, int $rings = 8): MeshGeometry
    {
        $cylinderHalf = max(0.0, ($height - $radius * 2.0) * 0.5);
        $vertices = new FloatBuffer();
        $indices = new UIntBuffer();

        // Top hemisphere
        for ($j = 0; $j <= $rings; $j++) {
            $theta = $j * (M_PI * 0.5) / $rings;
            $sinTheta = sin($theta);
            $cosTheta = cos($theta);

            for ($i = 0; $i <= $segments; $i++) {
                $phi = $i * 2.0 * M_PI / $segments;
                $sinPhi = sin($phi);
                $cosPhi = cos($phi);

                $nx = $sinTheta * $cosPhi;
                $ny = $cosTheta;
                $nz = $sinTheta * $sinPhi;

                self::pushVertex(
                    $vertices,
                    $nx * $radius, $ny * $radius + $cylinderHalf, $nz * $radius,
                    $nx, $ny, $nz,
                    $i / $segments, 0.5 - ($j / ($rings * 2)),
                    -$sinPhi, 0, $cosPhi, 1.0
                );
            }
        }

        // Cylinder section (2 rings)
        for ($row = 0; $row <= 1; $row++) {
            $y = $row === 0 ? $cylinderHalf : -$cylinderHalf;
            for ($i = 0; $i <= $segments; $i++) {
                $phi = $i * 2.0 * M_PI / $segments;
                $cos = cos($phi);
                $sin = sin($phi);
                self::pushVertex($vertices, $cos * $radius, $y, $sin * $radius, $cos, 0, $sin, $i / $segments, 0.5, -$sin, 0, $cos, 1.0);
            }
        }

        // Bottom hemisphere
        for ($j = 0; $j <= $rings; $j++) {
            $theta = (M_PI * 0.5) + $j * (M_PI * 0.5) / $rings;
            $sinTheta = sin($theta);
            $cosTheta = cos($theta);

            for ($i = 0; $i <= $segments; $i++) {
                $phi = $i * 2.0 * M_PI / $segments;
                $sinPhi = sin($phi);
                $cosPhi = cos($phi);

                $nx = $sinTheta * $cosPhi;
                $ny = $cosTheta;
                $nz = $sinTheta * $sinPhi;

                self::pushVertex(
                    $vertices,
                    $nx * $radius, $ny * $radius - $cylinderHalf, $nz * $radius,
                    $nx, $ny, $nz,
                    $i / $segments, 0.5 + ($j / ($rings * 2)),
                    -$sinPhi, 0, $cosPhi, 1.0
                );
            }
        }

        // Index all ring strips
        $totalRings = ($rings + 1) + 2 + ($rings + 1);
        $stride = $segments + 1;

        for ($j = 0; $j < $totalRings - 1; $j++) {
            for ($i = 0; $i < $segments; $i++) {
                $a = $j * $stride + $i;
                $b = $a + $stride;
                $indices->push($a); $indices->push($b); $indices->push($a + 1);
                $indices->push($b); $indices->push($b + 1); $indices->push($a + 1);
            }
        }

        $totalHalf = $height * 0.5;
        return new MeshGeometry(
            $vertices, $indices,
            new AABB(new Vec3(-$radius, -$totalHalf, -$radius), new Vec3($radius, $totalHalf, $radius))
        );
    }

    /**
     * Torus on XZ plane centered at origin
     */
    public static function generateTorus(float $majorRadius = 0.35, float $minorRadius = 0.15, int $majorSegments = 32, int $minorSegments = 16): MeshGeometry
    {
        $vertices = new FloatBuffer();
        $indices = new UIntBuffer();

        for ($j = 0; $j <= $majorSegments; $j++) {
            $theta = $j * 2.0 * M_PI / $majorSegments;
            $cosTheta = cos($theta);
            $sinTheta = sin($theta);

            for ($i = 0; $i <= $minorSegments; $i++) {
                $phi = $i * 2.0 * M_PI / $minorSegments;
                $cosPhi = cos($phi);
                $sinPhi = sin($phi);

                self::pushVertex(
                    $vertices,
                    ($majorRadius + $minorRadius * $cosPhi) * $cosTheta,
                    $minorRadius * $sinPhi,
                    ($majorRadius + $minorRadius * $cosPhi) * $sinTheta,
                    $cosPhi * $cosTheta, $sinPhi, $cosPhi * $sinTheta,
                    $j / $majorSegments, $i / $minorSegments,
                    -$sinTheta, 0.0, $cosTheta, 1.0
                );
            }
        }

        $stride = $minorSegments + 1;
        for ($j = 0; $j < $majorSegments; $j++) {
            for ($i = 0; $i < $minorSegments; $i++) {
                $a = $j * $stride + $i;
                $b = $a + $stride;
                $indices->push($a); $indices->push($b); $indices->push($a + 1);
                $indices->push($b); $indices->push($b + 1); $indices->push($a + 1);
            }
        }

        $outerR = $majorRadius + $minorRadius;
        return new MeshGeometry(
            $vertices, $indices,
            new AABB(new Vec3(-$outerR, -$minorRadius, -$outerR), new Vec3($outerR, $minorRadius, $outerR))
        );
    }

    // -----------------------------------------------------------------------
    //  Internals
    // -----------------------------------------------------------------------

    private static function pushVertex(
        FloatBuffer $buffer,
        float $px, float $py, float $pz,
        float $nx, float $ny, float $nz,
        float $u, float $v,
        float $tx, float $ty, float $tz, float $tw,
    ): void {
        $buffer->push($px); $buffer->push($py); $buffer->push($pz);
        $buffer->push($nx); $buffer->push($ny); $buffer->push($nz);
        $buffer->push($u); $buffer->push($v);
        $buffer->push($tx); $buffer->push($ty); $buffer->push($tz); $buffer->push($tw);
    }

    /**
     * Uploads MeshGeometry to GPU and wraps in Model3D
     */
    private function uploadGeometry(string $name, Material $material, MeshGeometry $geometry): Model3D
    {
        $mesh = new Mesh3D($this->gl, $material, $geometry->aabb);
        $mesh->uploadVertices($geometry->vertices);
        $mesh->uploadIndices($geometry->indices);

        $model = new Model3D($name);
        $model->addMesh($mesh);
        $model->recalculateAABB();

        return $model;
    }
}
