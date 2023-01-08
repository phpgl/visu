<?php

namespace VISU\Graphics\Rendering;

use VISU\Graphics\Exception\PipelineContainerException;

class PipelineContainer
{
    /**
     * @var array<class-string, mixed>
     */
    private array $storage = [];

    /**
     * Creates an instance of class T and stores it in the container.
     * 
     * @template T
     * @param class-string<T> $className
     * @return T
     */
    public function create(string $className)
    {
        if (isset($this->storage[$className])) {
            throw new PipelineContainerException("Pipeline container already contains an instance of class: " . $className);
        }

        $this->storage[$className] = new $className();

        return $this->storage[$className];
    }

    /**
     * Sets an instance of class T in the container.
     * 
     * @template T
     * @param T $instance
     */
    public function set($instance): void
    {
        $className = get_class($instance);

        if (isset($this->storage[$className])) {
            throw new PipelineContainerException("Pipeline container already contains an instance of class: " . $className);
        }

        $this->storage[$className] = $instance;
    }
    
    /**
     * Returns an instance of class T from the container.
     * 
     * @template T
     * @param class-string<T> $className
     * @return T
     */
    public function get(string $className)
    {
        if (!isset($this->storage[$className])) {
            throw new PipelineContainerException("Object of class {$className} does not exist, resources must be created first");
        }

        return $this->storage[$className];
    }

    /**
     * Returns true if an instance of class T exists in the container.
     * 
     * @template T
     * @param class-string<T> $className
     * @return bool
     */
    public function has(string $className): bool
    {
        return isset($this->storage[$className]);
    }

    /**
     * Removes an instance of class T from the container.
     * 
     * @template T
     * @param class-string<T> $className
     */
    public function remove(string $className): void
    {
        unset($this->storage[$className]);
    }

    /**
     * Removes all instances from the container.
     */
    public function clear(): void
    {
        $this->storage = [];
    }
}
