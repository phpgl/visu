<?php

namespace VISU\Graphics\Loader;

use GL\Buffer\FloatBuffer;
use GL\Buffer\UIntBuffer;
use GL\Math\Vec3;
use GL\Math\Vec4;
use VISU\Exception\VISUException;
use VISU\Geo\AABB;
use VISU\Graphics\GLState;
use VISU\Graphics\Material;
use VISU\Graphics\Mesh3D;
use VISU\Graphics\Model3D;
use VISU\Graphics\Texture;
use VISU\Graphics\TextureOptions;

class GltfLoader
{
    public function __construct(private GLState $gl)
    {
    }

    /**
     * Loads a glTF (.gltf) or GLB (.glb) file and returns a Model3D
     */
    public function load(string $path): Model3D
    {
        if (!file_exists($path)) {
            throw new VISUException("glTF file not found: {$path}");
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'glb') {
            return $this->loadGlb($path);
        }

        return $this->loadGltf($path);
    }

    /**
     * Loads a .gltf (JSON + external binary) file
     */
    private function loadGltf(string $path): Model3D
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new VISUException("Failed to read glTF file: {$path}");
        }
        $json = json_decode($contents, true);
        if (!is_array($json)) {
            throw new VISUException("Failed to parse glTF JSON: {$path}");
        }

        $baseDir = dirname($path);

        // load external buffers
        $buffers = [];
        foreach ($json['buffers'] ?? [] as $bufferDef) {
            if (isset($bufferDef['uri'])) {
                if (str_starts_with($bufferDef['uri'], 'data:')) {
                    $buffers[] = $this->decodeDataUri($bufferDef['uri']);
                } else {
                    $bufferPath = $baseDir . '/' . $bufferDef['uri'];
                    if (!file_exists($bufferPath)) {
                        throw new VISUException("glTF buffer file not found: {$bufferPath}");
                    }
                    $data = file_get_contents($bufferPath);
                    if ($data === false) {
                        throw new VISUException("Failed to read glTF buffer: {$bufferPath}");
                    }
                    $buffers[] = $data;
                }
            }
        }

        return $this->buildModel($json, $buffers, $baseDir, basename($path));
    }

    /**
     * Loads a .glb (binary glTF) file
     */
    private function loadGlb(string $path): Model3D
    {
        $data = file_get_contents($path);
        if ($data === false) {
            throw new VISUException("Failed to read GLB file: {$path}");
        }
        $offset = 0;

        if (strlen($data) < 12) {
            throw new VISUException("Invalid GLB file (too short): {$path}");
        }

        // header: magic(4) + version(4) + length(4) = 12 bytes
        /** @var array{magic: int, version: int, length: int} $header */
        $header = unpack('Vmagic/Vversion/Vlength', $data, $offset);
        $offset += 12;

        if ($header['magic'] !== 0x46546C67) {
            throw new VISUException("Invalid GLB magic number in: {$path}");
        }

        if ($header['version'] !== 2) {
            throw new VISUException("Unsupported GLB version {$header['version']}, expected 2");
        }

        $json = null;
        $buffers = [];

        while ($offset < $header['length']) {
            /** @var array{chunkLength: int, chunkType: int} $chunkHeader */
            $chunkHeader = unpack('VchunkLength/VchunkType', $data, $offset);
            $offset += 8;
            $chunkData = substr($data, $offset, $chunkHeader['chunkLength']);
            $offset += $chunkHeader['chunkLength'];

            if ($chunkHeader['chunkType'] === 0x4E4F534A) {
                // JSON chunk
                $json = json_decode($chunkData, true);
            } elseif ($chunkHeader['chunkType'] === 0x004E4942) {
                // BIN chunk
                $buffers[] = $chunkData;
            }
        }

        if ($json === null) {
            throw new VISUException("No JSON chunk found in GLB: {$path}");
        }

        return $this->buildModel($json, $buffers, dirname($path), basename($path));
    }

    /**
     * Builds a Model3D from parsed glTF JSON and binary buffers
     *
     * @param array<string, mixed> $json
     * @param array<string> $buffers
     */
    private function buildModel(array $json, array $buffers, string $baseDir, string $name): Model3D
    {
        $model = new Model3D($name);

        // parse materials
        $materials = $this->parseMaterials($json, $buffers, $baseDir);

        // process all meshes from the default scene
        $sceneIndex = $json['scene'] ?? 0;
        $scene = $json['scenes'][$sceneIndex] ?? null;

        if ($scene === null) {
            throw new VISUException("No scene found in glTF");
        }

        foreach ($scene['nodes'] ?? [] as $nodeIndex) {
            $this->processNode($json, $buffers, $nodeIndex, $materials, $model);
        }

        $model->recalculateAABB();
        return $model;
    }

    /**
     * Recursively processes a glTF node and its children
     *
     * @param array<string, mixed> $json
     * @param array<string> $buffers
     * @param array<Material> $materials
     */
    private function processNode(array $json, array $buffers, int $nodeIndex, array $materials, Model3D $model): void
    {
        $node = $json['nodes'][$nodeIndex] ?? null;
        if ($node === null) return;

        // process mesh if present
        if (isset($node['mesh'])) {
            $meshDef = $json['meshes'][$node['mesh']];
            foreach ($meshDef['primitives'] ?? [] as $primitive) {
                $mesh = $this->buildMesh($json, $buffers, $primitive, $materials);
                if ($mesh !== null) {
                    $model->addMesh($mesh);
                }
            }
        }

        // recurse children
        foreach ($node['children'] ?? [] as $childIndex) {
            $this->processNode($json, $buffers, $childIndex, $materials, $model);
        }
    }

    /**
     * Builds a Mesh3D from a glTF mesh primitive
     *
     * @param array<string, mixed> $json
     * @param array<string> $buffers
     * @param array<string, mixed> $primitive
     * @param array<Material> $materials
     */
    private function buildMesh(array $json, array $buffers, array $primitive, array $materials): ?Mesh3D
    {
        $attributes = $primitive['attributes'] ?? [];

        // position is required
        if (!isset($attributes['POSITION'])) return null;

        $positions = $this->readAccessor($json, $buffers, $attributes['POSITION']);
        $normals = isset($attributes['NORMAL'])
            ? $this->readAccessor($json, $buffers, $attributes['NORMAL'])
            : null;
        $uvs = isset($attributes['TEXCOORD_0'])
            ? $this->readAccessor($json, $buffers, $attributes['TEXCOORD_0'])
            : null;
        $tangents = isset($attributes['TANGENT'])
            ? $this->readAccessor($json, $buffers, $attributes['TANGENT'])
            : null;

        // material
        $materialIndex = $primitive['material'] ?? -1;
        $material = $materials[$materialIndex] ?? new Material('default');

        // compute AABB from positions
        $accessor = $json['accessors'][$attributes['POSITION']];
        $aabb = new AABB(
            new Vec3(
                $accessor['min'][0] ?? -1.0,
                $accessor['min'][1] ?? -1.0,
                $accessor['min'][2] ?? -1.0,
            ),
            new Vec3(
                $accessor['max'][0] ?? 1.0,
                $accessor['max'][1] ?? 1.0,
                $accessor['max'][2] ?? 1.0,
            )
        );

        // build interleaved vertex buffer: pos(3) + normal(3) + uv(2) + tangent(4) = 12 floats
        $vertexCount = count($positions) / 3;
        $vertexData = new FloatBuffer();

        for ($i = 0; $i < $vertexCount; $i++) {
            // position
            $vertexData->push($positions[$i * 3 + 0]);
            $vertexData->push($positions[$i * 3 + 1]);
            $vertexData->push($positions[$i * 3 + 2]);

            // normal
            $vertexData->push($normals !== null ? $normals[$i * 3 + 0] : 0.0);
            $vertexData->push($normals !== null ? $normals[$i * 3 + 1] : 1.0);
            $vertexData->push($normals !== null ? $normals[$i * 3 + 2] : 0.0);

            // uv
            $vertexData->push($uvs !== null ? $uvs[$i * 2 + 0] : 0.0);
            $vertexData->push($uvs !== null ? $uvs[$i * 2 + 1] : 0.0);

            // tangent
            $vertexData->push($tangents !== null ? $tangents[$i * 4 + 0] : 1.0);
            $vertexData->push($tangents !== null ? $tangents[$i * 4 + 1] : 0.0);
            $vertexData->push($tangents !== null ? $tangents[$i * 4 + 2] : 0.0);
            $vertexData->push($tangents !== null ? $tangents[$i * 4 + 3] : 1.0);
        }

        $mesh = new Mesh3D($this->gl, $material, $aabb);
        $mesh->uploadVertices($vertexData);

        // indices
        if (isset($primitive['indices'])) {
            $indexData = $this->readAccessor($json, $buffers, $primitive['indices']);
            $indexBuffer = new UIntBuffer();
            foreach ($indexData as $idx) {
                $indexBuffer->push((int)$idx);
            }
            $mesh->uploadIndices($indexBuffer);
        }

        return $mesh;
    }

    /**
     * Reads data from a glTF accessor
     *
     * @param array<string, mixed> $json
     * @param array<string> $buffers
     * @return array<float|int>
     */
    private function readAccessor(array $json, array $buffers, int $accessorIndex): array
    {
        $accessor = $json['accessors'][$accessorIndex];
        $bufferViewIndex = $accessor['bufferView'] ?? null;
        $byteOffset = $accessor['byteOffset'] ?? 0;
        $count = $accessor['count'];
        $componentType = $accessor['componentType'];
        $type = $accessor['type'];

        $componentCount = match ($type) {
            'SCALAR' => 1,
            'VEC2' => 2,
            'VEC3' => 3,
            'VEC4' => 4,
            'MAT2' => 4,
            'MAT3' => 9,
            'MAT4' => 16,
            default => throw new VISUException("Unknown accessor type: {$type}"),
        };

        if ($bufferViewIndex === null) {
            return array_fill(0, $count * $componentCount, 0);
        }

        $bufferView = $json['bufferViews'][$bufferViewIndex];
        $bufferIndex = $bufferView['buffer'];
        $viewByteOffset = ($bufferView['byteOffset'] ?? 0) + $byteOffset;
        $byteStride = $bufferView['byteStride'] ?? 0;

        $buffer = $buffers[$bufferIndex];
        $result = [];

        $componentSize = match ($componentType) {
            5120 => 1,  // BYTE
            5121 => 1,  // UNSIGNED_BYTE
            5122 => 2,  // SHORT
            5123 => 2,  // UNSIGNED_SHORT
            5125 => 4,  // UNSIGNED_INT
            5126 => 4,  // FLOAT
            default => throw new VISUException("Unknown component type: {$componentType}"),
        };

        $elementSize = $componentSize * $componentCount;
        $stride = $byteStride > 0 ? $byteStride : $elementSize;

        for ($i = 0; $i < $count; $i++) {
            $elementOffset = $viewByteOffset + $i * $stride;

            for ($j = 0; $j < $componentCount; $j++) {
                $compOffset = $elementOffset + $j * $componentSize;
                /** @var array<int, int|float> $unpacked */
                $unpacked = match ($componentType) {
                    5120 => unpack('c', $buffer, $compOffset),
                    5121 => unpack('C', $buffer, $compOffset),
                    5122 => unpack('v', $buffer, $compOffset),
                    5123 => unpack('v', $buffer, $compOffset),
                    5125 => unpack('V', $buffer, $compOffset),
                    5126 => unpack('g', $buffer, $compOffset), // little-endian float
                    default => [1 => 0],
                };
                $result[] = $unpacked[1];
            }
        }

        return $result;
    }

    /**
     * Parses glTF materials
     *
     * @param array<string, mixed> $json
     * @param array<string> $buffers
     * @return array<int, Material>
     */
    private function parseMaterials(array $json, array $buffers, string $baseDir): array
    {
        $materials = [];

        foreach ($json['materials'] ?? [] as $index => $matDef) {
            $name = $matDef['name'] ?? "material_{$index}";
            $pbr = $matDef['pbrMetallicRoughness'] ?? [];

            $baseColorFactor = $pbr['baseColorFactor'] ?? [1, 1, 1, 1];
            $material = new Material(
                name: $name,
                albedoColor: new Vec4($baseColorFactor[0], $baseColorFactor[1], $baseColorFactor[2], $baseColorFactor[3]),
                metallic: $pbr['metallicFactor'] ?? 1.0,
                roughness: $pbr['roughnessFactor'] ?? 1.0,
            );

            // load textures
            if (isset($pbr['baseColorTexture'])) {
                $material->albedoTexture = $this->loadGltfTexture($json, $buffers, $baseDir, $pbr['baseColorTexture']['index'], true);
            }
            if (isset($pbr['metallicRoughnessTexture'])) {
                $material->metallicRoughnessTexture = $this->loadGltfTexture($json, $buffers, $baseDir, $pbr['metallicRoughnessTexture']['index'], false);
            }
            if (isset($matDef['normalTexture'])) {
                $material->normalTexture = $this->loadGltfTexture($json, $buffers, $baseDir, $matDef['normalTexture']['index'], false);
            }
            if (isset($matDef['occlusionTexture'])) {
                $material->aoTexture = $this->loadGltfTexture($json, $buffers, $baseDir, $matDef['occlusionTexture']['index'], false);
            }
            if (isset($matDef['emissiveTexture'])) {
                $material->emissiveTexture = $this->loadGltfTexture($json, $buffers, $baseDir, $matDef['emissiveTexture']['index'], true);
            }

            $emissiveFactor = $matDef['emissiveFactor'] ?? [0, 0, 0];
            $material->emissiveColor = new Vec3($emissiveFactor[0], $emissiveFactor[1], $emissiveFactor[2]);

            $material->alphaMode = $matDef['alphaMode'] ?? 'OPAQUE';
            $material->alphaCutoff = $matDef['alphaCutoff'] ?? 0.5;
            $material->doubleSided = $matDef['doubleSided'] ?? false;

            $materials[$index] = $material;
        }

        return $materials;
    }

    /**
     * Loads a texture referenced by a glTF texture index
     *
     * @param array<string, mixed> $json
     * @param array<string> $buffers
     */
    private function loadGltfTexture(array $json, array $buffers, string $baseDir, int $textureIndex, bool $srgb): ?Texture
    {
        $textureDef = $json['textures'][$textureIndex] ?? null;
        if ($textureDef === null) return null;

        $imageIndex = $textureDef['source'] ?? null;
        if ($imageIndex === null) return null;

        $imageDef = $json['images'][$imageIndex] ?? null;
        if ($imageDef === null) return null;

        $options = new TextureOptions();
        $options->isSRGB = $srgb;

        // apply sampler settings if present
        if (isset($textureDef['sampler'])) {
            $sampler = $json['samplers'][$textureDef['sampler']] ?? [];
            if (isset($sampler['magFilter'])) $options->magFilter = $sampler['magFilter'];
            if (isset($sampler['minFilter'])) $options->minFilter = $sampler['minFilter'];
            if (isset($sampler['wrapS'])) $options->wrapS = $this->convertGltfWrap($sampler['wrapS']);
            if (isset($sampler['wrapT'])) $options->wrapT = $this->convertGltfWrap($sampler['wrapT']);
        }

        $texture = new Texture($this->gl, "gltf_{$textureIndex}");

        if (isset($imageDef['uri'])) {
            if (str_starts_with($imageDef['uri'], 'data:')) {
                // embedded base64 image — not supported yet, skip
                return null;
            }
            $imagePath = $baseDir . '/' . $imageDef['uri'];
            $texture->loadFromFile($imagePath, $options);
        } elseif (isset($imageDef['bufferView'])) {
            // image embedded in binary buffer — write to temp file and load
            $bufferView = $json['bufferViews'][$imageDef['bufferView']];
            $bufferData = $buffers[$bufferView['buffer']];
            $imageData = substr($bufferData, $bufferView['byteOffset'] ?? 0, $bufferView['byteLength']);

            $tmpFile = tempnam(sys_get_temp_dir(), 'gltf_img_');
            $ext = match ($imageDef['mimeType'] ?? '') {
                'image/png' => '.png',
                'image/jpeg' => '.jpg',
                default => '.png',
            };
            $tmpFile .= $ext;
            file_put_contents($tmpFile, $imageData);
            $texture->loadFromFile($tmpFile, $options);
            unlink($tmpFile);
        } else {
            return null;
        }

        return $texture;
    }

    /**
     * Converts glTF wrap mode constants to OpenGL constants
     */
    private function convertGltfWrap(int $gltfWrap): int
    {
        return match ($gltfWrap) {
            33071 => GL_CLAMP_TO_EDGE,
            33648 => GL_MIRRORED_REPEAT,
            10497 => GL_REPEAT,
            default => GL_REPEAT,
        };
    }

    /**
     * Decodes a data URI (data:application/octet-stream;base64,...)
     */
    private function decodeDataUri(string $uri): string
    {
        $commaPos = strpos($uri, ',');
        if ($commaPos === false) {
            throw new VISUException("Invalid data URI");
        }
        return base64_decode(substr($uri, $commaPos + 1));
    }
}
