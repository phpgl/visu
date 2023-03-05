<?php

namespace VISU\System\VISULowPoly;

use GL\Buffer\FloatBuffer;
use GL\Geometry\ObjFileParser;
use VISU\Exception\VISUException;
use VISU\Geo\AABB;
use VISU\Graphics\GLState;

class LPObjLoader
{
    public function __construct(private GLState $gl)
    {
    }

    /**
     * Loads a single object file and returns it
     * 
     * @param string $path 
     * @return LPModel 
     */
    private function loadFile(string $path, FloatBuffer $buffer, LPVertexBuffer $vb, int &$vertexOffset, float $scaleModifier = 1.0) : LPModel
    {
        $source = new ObjFileParser($path);
        
        $sourceMeshes = $source->getMeshes('pn');

        $model = new LPModel(basename($path));

        foreach ($sourceMeshes as $sourceMesh) {

            $material = new LPMaterial(
                $sourceMesh->material->name,
                $sourceMesh->material->diffuse,
                $sourceMesh->material->shininess
            );

            // this is EXTREMELY inefficient, but it's the easiest way to get it working
            // for now until we add some sort of buffer merging into PHP-GLFW or something
            for ($i = 0; $i < $sourceMesh->vertices->size(); $i+=6) {
                $buffer->push($sourceMesh->vertices[$i + 0] * $scaleModifier);
                $buffer->push($sourceMesh->vertices[$i + 1] * $scaleModifier);
                $buffer->push($sourceMesh->vertices[$i + 2] * $scaleModifier);

                $buffer->push($sourceMesh->vertices[$i + 3]);
                $buffer->push($sourceMesh->vertices[$i + 4]);
                $buffer->push($sourceMesh->vertices[$i + 5]);
            }

            $mesh = new LPMesh(
                $material,
                $vb,
                $vertexOffset,
                $sourceMesh->vertices->size() / 6,
                new AABB(
                    $sourceMesh->aabbMin * $scaleModifier,
                    $sourceMesh->aabbMax * $scaleModifier,
                )
            );

            $vertexOffset += $sourceMesh->vertices->size() / 6;

            $model->meshes[] = $mesh;
        }

        // dont forget to recalculate the AABB
        $model->recalculateAABB();

        return $model;
    }

    /**
     * Loads all object files in a given directory and returns them in assoc array
     * 
     * @param string $directory The directory to load the files from
     * @param LPModelCollection $collection The collection to store the models in
     * @param float $scaleModifier The scale modifier to apply to the models while loading 
     * @return void
     */
    public function loadAllInDirectory(string $directory, LPModelCollection $collection, float $scaleModifier = 1.0): void
    {
        if (!is_dir($directory)) {
            throw new VISUException('Cannot load objects, directory does not exist: ' . $directory);
        }

        // create a vertex buffer to store all the objects in
        $vb = new LPVertexBuffer($this->gl);
        $vertices = new FloatBuffer();
        $indexOffset = 0;

        $files = scandir($directory) ?: [];

        foreach ($files as $file) {
            if (substr($file, -4) === '.obj') {
                $collection->add($this->loadFile($directory . '/' . $file, $vertices, $vb, $indexOffset, $scaleModifier));
            }
        }

        // upload the data to the GPU
        $vb->uploadData($vertices);
    }
}
