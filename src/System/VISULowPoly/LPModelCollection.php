<?php

namespace VISU\System\VISULowPoly;

use VISU\Exception\VISUException;

class LPModelCollection
{
    /**
     * We expose this as public to avoid the overhead of a getter,
     * When you use this property directly, you should know what you're doing
     * 
     * @var array<string, LPModel>
     */
    public array $models = [];

    /**
     * Adds a model to the collection, throws an exception if the model already exists
     */
    public function add(LPModel $model) : void
    {
        if (isset($this->models[$model->name])) {
            throw new VISUException("Model with name '{$model->name}' already exists in this collection");
        }

        $this->models[$model->name] = $model;
    }

    /**
     * Returns true if the model exists in the collection
     */
    public function has(string $name) : bool
    {
        return isset($this->models[$name]);
    }

    /**
     * Returns a model by name, throws an exception if the model doesn't exist
     */
    public function get(string $name) : LPModel
    {
        if (!isset($this->models[$name])) {
            throw new VISUException("Model with name '{$name}' does not exist in this collection");
        }

        return $this->models[$name];
    }
}