<?php

namespace VISU\System\VISULowPoly;

use GL\Buffer\FloatBuffer;
use GL\Geometry\ObjFileParser;
use GL\Math\Vec3;
use VISU\Exception\VISUException;
use VISU\Geo\AABB;
use VISU\Graphics\GLState;

class LPObjLoader
{
    public function __construct(private GLState $gl)
    {
    }

    /**
     * Imports a mesh from a source buffer into the given LowPoly vertex buffer
     * 
     * The source mesh is expected to be in the format of:
     *  - [3: pos, 3: normal] = stride of 6 floats per vertex
     * 
     * The material will be baked into the vertex buffer as well.
     */
    public function importMesh(
        FloatBuffer $sourceMesh, 
        LPMaterial $material, 
        LPVertexBuffer $vb, 
        float $scaleModifier = 1.0
    ) : LPMesh
    {
        // this is quite inefficient, as we create a temporary buffer for each mesh
        // and then copy the data over. But this only happens during loading, and therefor
        // doesnt really impact runtime performance so i keep this simpler approach for now.
        $tmpBuffer = new FloatBuffer();

        $aabbMin = new Vec3(PHP_FLOAT_MAX, PHP_FLOAT_MAX, PHP_FLOAT_MAX);
        $aabbMax = new Vec3(-PHP_FLOAT_MAX, -PHP_FLOAT_MAX, -PHP_FLOAT_MAX);
        
        for ($i = 0; $i < $sourceMesh->size(); $i+=6) {
            // position
            $tmpBuffer->push($sourceMesh[$i + 0] * $scaleModifier);
            $tmpBuffer->push($sourceMesh[$i + 1] * $scaleModifier);
            $tmpBuffer->push($sourceMesh[$i + 2] * $scaleModifier);

            // normal
            $tmpBuffer->push($sourceMesh[$i + 3]);
            $tmpBuffer->push($sourceMesh[$i + 4]);
            $tmpBuffer->push($sourceMesh[$i + 5]);

            // color
            $tmpBuffer->push($material->color->x);
            $tmpBuffer->push($material->color->y);
            $tmpBuffer->push($material->color->z);

            // roughness
            $tmpBuffer->push($material->roughness);
            
            // metallic
            $tmpBuffer->push($material->metallic);

            // emissive
            if ($material->emissive) {
                $tmpBuffer->push($material->emissive->x);
                $tmpBuffer->push($material->emissive->y);
                $tmpBuffer->push($material->emissive->z);
            } else {
                $tmpBuffer->push(0.0);
                $tmpBuffer->push(0.0);
                $tmpBuffer->push(0.0);
            }

            // update AABB
            $aabbMin->x = min($aabbMin->x, $sourceMesh[$i + 0] * $scaleModifier);
            $aabbMin->y = min($aabbMin->y, $sourceMesh[$i + 1] * $scaleModifier);
            $aabbMin->z = min($aabbMin->z, $sourceMesh[$i + 2] * $scaleModifier);
            $aabbMax->x = max($aabbMax->x, $sourceMesh[$i + 0] * $scaleModifier);
            $aabbMax->y = max($aabbMax->y, $sourceMesh[$i + 1] * $scaleModifier);
            $aabbMax->z = max($aabbMax->z, $sourceMesh[$i + 2] * $scaleModifier);
        }

        $mesh = new LPMesh(
            $material,
            $vb,
            $vb->appennd($tmpBuffer),
            $sourceMesh->size() / 6,
            new AABB(
                $aabbMin,
                $aabbMax,
            )
        );

        return $mesh;
    }

    private function shininessToRoughness(float $ns): float
    {
        $ns = max(0.0, min(1000.0, $ns));
        $roughness = 1.0 - sqrt($ns / 1000.0);
        return max(0.0, min(1.0, $roughness));
    }

    private function vec3IsNonZero(Vec3 $v, float $eps = 1e-6): bool
    {
        return (abs($v->x) > $eps) || (abs($v->y) > $eps) || (abs($v->z) > $eps);
    }

    /**
     * Loads a single object file into the given model collection
     */
    private function loadFile(
        string $path, 
        string $modelName, 
        LPVertexBuffer $vb, 
        LPModelCollection $collection, 
        float $scaleModifier = 1.0
    ) : void
    {
        $source = new ObjFileParser($path);
        
        $sourceMeshes = $source->getMeshes('pn');
        $combinedModel = new LPModel($modelName);
        $combinedModelBaseName = basename($modelName);

        foreach ($sourceMeshes as $sourceMesh) {
            $srcMat = $sourceMesh->material;
            $roughness = $this->shininessToRoughness($srcMat->shininess);

            $material = new LPMaterial(
                $srcMat->name,
                $srcMat->diffuse,
                $roughness,
                metallic: ($srcMat->illuminationModel === 3) ? 1.0 : 0.0
            );

            // set emissive if non zero
            if ($srcMat->emissive && $this->vec3IsNonZero($srcMat->emissive)) {
                $material->emissive = $srcMat->emissive;
            }

            $mesh = $this->importMesh($sourceMesh->vertices, $material, $vb, $scaleModifier);
            $combinedModel->meshes[] = $mesh;
        }

        // dont forget to recalculate the AABB
        $combinedModel->recalculateAABB();

        // and push to collection
        $collection->add($combinedModel);

        // we have to do the same for all sub objects in the file
        // so we could use individual objects inside of a model as separate models
        // this will cause duplicated vertex data in the buffer, but makes access soo much easier
        foreach($source->groups as $objectGroup) 
        {
            $objectModel = new LPModel($modelName . '.' . $objectGroup->name);

            // skip duplicates in sub objects, or objects of the same name as the combined model
            if ($collection->has($objectModel->name) || $combinedModelBaseName === $objectGroup->name) {
                continue;
            }

            $sourceMeshes = $source->getMeshes('pn', $objectGroup);

            foreach ($sourceMeshes as $sourceMesh) {
                $srcMat = $sourceMesh->material;
                $roughness = $this->shininessToRoughness($srcMat->shininess);

                $material = new LPMaterial(
                    $srcMat->name,
                    $srcMat->diffuse,
                    $roughness,
                    metallic: ($srcMat->illuminationModel === 3) ? 1.0 : 0.0
                );

                // set emissive if non zero
                if ($srcMat->emissive && $this->vec3IsNonZero($srcMat->emissive)) {
                    $material->emissive = $srcMat->emissive;
                }
                
                $mesh = $this->importMesh($sourceMesh->vertices, $material, $vb, $scaleModifier);
                $objectModel->meshes[] = $mesh;
            }

            // dont forget to recalculate the AABB
            $objectModel->recalculateAABB();

            // and push to collection
            $collection->add($objectModel);
        }
    }

    /**
     * Loads all object files in a given directory and returns them in assoc array
     * 
     * @param string $directory                 The directory to load the files from
     * @param LPModelCollection $collection     The collection to store the models in
     * @param float $scaleModifier              The scale modifier to apply to the models while loading 
     * @param LPVertexBuffer|null $vertexBuffer The vertex buffer to use
     * @return void
     */
    public function loadAllInDirectory(string $directory, LPModelCollection $collection, float $scaleModifier = 1.0, ?LPVertexBuffer $vertexBuffer = null): void
    {
        if (!is_dir($directory)) {
            throw new VISUException('Cannot load objects, directory does not exist: ' . $directory);
        }

        // create a vertex buffer to store all the objects in, or use the provided one
        $vb = $vertexBuffer ?? new LPVertexBuffer($this->gl);

        // use recursive iterator to scan all .obj files
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'obj') {
                // calculate relative path for model name
                $basePath = realpath($directory);
                $filePath = realpath($file->getPathname());
                
                if ($basePath === false || $filePath === false) {
                    continue; // skip if paths cannot be resolved
                }
                
                $relativePath = substr($filePath, strlen($basePath) + 1);
                $modelName = substr($relativePath, 0, -4); // remove .obj extension
                
                $this->loadFile(
                    $file->getPathname(), 
                    $modelName, 
                    $vb, 
                    $collection, 
                    $scaleModifier
                );
            }
        }

        // upload the data to the GPU
        $vb->upload();
    }
}
