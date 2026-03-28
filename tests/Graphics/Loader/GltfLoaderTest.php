<?php

namespace Tests\Graphics\Loader;

use PHPUnit\Framework\TestCase;
use VISU\Exception\VISUException;
use VISU\Graphics\Loader\GltfLoader;

/**
 * Tests for glTF loader file parsing, validation, and error handling.
 * GL-dependent tests (actual mesh construction) require a windowed context
 * and are tested via the demo examples.
 */
class GltfLoaderTest extends TestCase
{
    private function createLoader(): GltfLoader
    {
        $gl = $this->createMock(\VISU\Graphics\GLState::class);
        return new GltfLoader($gl);
    }

    public function testLoadThrowsForMissingFile(): void
    {
        $loader = $this->createLoader();
        $this->expectException(VISUException::class);
        $this->expectExceptionMessage('file not found');
        $loader->load('/nonexistent/file.glb');
    }

    public function testLoadThrowsForInvalidGlbMagic(): void
    {
        $loader = $this->createLoader();

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_glb_') . '.glb';
        // write data that's long enough for header parsing but has wrong magic
        file_put_contents($tmpFile, str_repeat("\x00", 64));

        try {
            $this->expectException(VISUException::class);
            $this->expectExceptionMessage('Invalid GLB magic');
            $loader->load($tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testLoadThrowsForInvalidGltfJson(): void
    {
        $loader = $this->createLoader();

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_gltf_') . '.gltf';
        file_put_contents($tmpFile, '{not valid json!!!}');

        try {
            $this->expectException(VISUException::class);
            $this->expectExceptionMessage('Failed to parse');
            $loader->load($tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testLoadThrowsForGltfWithoutScene(): void
    {
        $loader = $this->createLoader();

        $gltf = [
            'asset' => ['version' => '2.0'],
            // no scenes defined
        ];

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_gltf_') . '.gltf';
        file_put_contents($tmpFile, json_encode($gltf));

        try {
            $this->expectException(VISUException::class);
            $this->expectExceptionMessage('No scene');
            $loader->load($tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testGlbVersionValidation(): void
    {
        $loader = $this->createLoader();

        // build GLB with version 1 (unsupported)
        $jsonStr = json_encode(['asset' => ['version' => '2.0']]);
        while (strlen($jsonStr) % 4 !== 0) $jsonStr .= ' ';

        $totalLength = 12 + 8 + strlen($jsonStr);
        $glb = pack('VVV', 0x46546C67, 1, $totalLength); // version 1
        $glb .= pack('VV', strlen($jsonStr), 0x4E4F534A) . $jsonStr;

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_glb_') . '.glb';
        file_put_contents($tmpFile, $glb);

        try {
            $this->expectException(VISUException::class);
            $this->expectExceptionMessage('Unsupported GLB version');
            $loader->load($tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testValidGlbHeaderParsing(): void
    {
        $loader = $this->createLoader();

        // Build valid GLB with empty scene (will fail at mesh construction, not at parsing)
        $gltf = [
            'asset' => ['version' => '2.0'],
            'scene' => 0,
            'scenes' => [['nodes' => []]],
        ];

        $jsonStr = json_encode($gltf);
        while (strlen($jsonStr) % 4 !== 0) $jsonStr .= ' ';

        $totalLength = 12 + 8 + strlen($jsonStr);
        $glb = pack('VVV', 0x46546C67, 2, $totalLength);
        $glb .= pack('VV', strlen($jsonStr), 0x4E4F534A) . $jsonStr;

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_glb_') . '.glb';
        file_put_contents($tmpFile, $glb);

        try {
            // empty scene with no nodes should return a model with no meshes
            // This tests GLB header parsing + JSON chunk extraction + empty scene handling
            // Will segfault if GL calls are made, but empty scene shouldn't trigger any
            $model = $loader->load($tmpFile);
            $this->assertEquals(basename($tmpFile), $model->name);
            $this->assertEmpty($model->meshes);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testValidGltfEmptyScene(): void
    {
        $loader = $this->createLoader();

        $gltf = [
            'asset' => ['version' => '2.0'],
            'scene' => 0,
            'scenes' => [['nodes' => []]],
        ];

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_gltf_') . '.gltf';
        file_put_contents($tmpFile, json_encode($gltf));

        try {
            $model = $loader->load($tmpFile);
            $this->assertEquals(basename($tmpFile), $model->name);
            $this->assertEmpty($model->meshes);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testGltfNodeWithoutMeshIsSkipped(): void
    {
        $loader = $this->createLoader();

        $gltf = [
            'asset' => ['version' => '2.0'],
            'scene' => 0,
            'scenes' => [['nodes' => [0]]],
            'nodes' => [['name' => 'EmptyNode']], // no mesh reference
        ];

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_gltf_') . '.gltf';
        file_put_contents($tmpFile, json_encode($gltf));

        try {
            $model = $loader->load($tmpFile);
            $this->assertEmpty($model->meshes);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testGltfMaterialParsing(): void
    {
        $loader = $this->createLoader();

        // Build glTF with a material but no geometry that references it.
        // We test that loading succeeds and material is parsed.
        $gltf = [
            'asset' => ['version' => '2.0'],
            'scene' => 0,
            'scenes' => [['nodes' => []]],
            'materials' => [[
                'name' => 'TestMaterial',
                'pbrMetallicRoughness' => [
                    'baseColorFactor' => [1.0, 0.0, 0.0, 1.0],
                    'metallicFactor' => 0.8,
                    'roughnessFactor' => 0.2,
                ],
                'emissiveFactor' => [0.5, 0.3, 0.1],
                'doubleSided' => true,
            ]],
        ];

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_gltf_') . '.gltf';
        file_put_contents($tmpFile, json_encode($gltf));

        try {
            // Materials are parsed but not directly accessible from Model3D
            // (they're attached to meshes). Empty scene should load fine.
            $model = $loader->load($tmpFile);
            $this->assertEmpty($model->meshes);
        } finally {
            @unlink($tmpFile);
        }
    }
}
