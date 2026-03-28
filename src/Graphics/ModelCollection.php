<?php

namespace VISU\Graphics;

use VISU\Exception\VISUException;

class ModelCollection
{
    /**
     * @var array<string, Model3D>
     */
    public array $models = [];

    public function add(Model3D $model): void
    {
        if (isset($this->models[$model->name])) {
            throw new VISUException("Model '{$model->name}' already exists in collection");
        }
        $this->models[$model->name] = $model;
    }

    public function has(string $name): bool
    {
        return isset($this->models[$name]);
    }

    public function get(string $name): Model3D
    {
        if (!isset($this->models[$name])) {
            throw new VISUException("Model '{$name}' not found in collection");
        }
        return $this->models[$name];
    }
}
