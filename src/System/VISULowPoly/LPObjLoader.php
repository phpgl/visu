<?php

namespace VISU\System\VISULowPoly;

use GL\Buffer\FloatBuffer;
use GL\Geometry\ObjFileParser;
use VISU\Exception\VISUException;
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
    private function loadFile(string $path, FloatBuffer $buffer, LPVertexBuffer $vb, int &$vertexOffset) : LPModel
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
            foreach($sourceMesh->vertices as $vertexValue) {
                $buffer->push($vertexValue);
            }

            $mesh = new LPMesh(
                $material,
                $vb,
                $vertexOffset,
                $sourceMesh->vertices->size() / 6
            );

            $vertexOffset += $sourceMesh->vertices->size() / 6;

            $model->meshes[] = $mesh;
        }

        return $model;
    }

    /**
     * Loads all object files in a given directory and returns them in assoc array
     * 
     * @param string $directory 
     * @return array<string, LPModel>
     */
    public function loadAllInDirectory(string $directory): array
    {
        if (!is_dir($directory)) {
            throw new VISUException('Cannot load objects, directory does not exist: ' . $directory);
        }

        // create a vertex buffer to store all the objects in
        $vb = new LPVertexBuffer($this->gl);
        $vertices = new FloatBuffer();
        $indexOffset = 0;

        $models = [];
        $files = scandir($directory);

        foreach ($files as $file) {
            if (substr($file, -4) === '.obj') {
                $model = $this->loadFile($directory . '/' . $file, $vertices, $vb, $indexOffset);
                $models[$model->name] = $model;
            }
        }

        $vb->uploadData($vertices);

        return $models;
    }
}
