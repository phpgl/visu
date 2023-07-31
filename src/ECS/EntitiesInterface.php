<?php

namespace VISU\ECS;

use Generator;

/**
 * An entity is nothing more then just a number on which components 
 * are attached indexed to.
 */
interface EntitiesInterface
{
    /**
     * Creates an entity and returns its ID
     */
    public function create() : int;

    /**
     * Returns boolean if the given entity ID is a valid one
     */
    public function valid(int $entity) : bool;

    /**
     * Prepares internal data structures for the given component
     * 
     * @param class-string              $componentClassName
     */
    public function registerComponent(string $componentClassName) : void;

    /**
     * @param class-string              $componentClassName
     * @return array<int>
     */
    public function list(string $componentClassName) : array;

    /**
     * @param class-string              $componentClassNames
     * @return array<int>
     */
    public function listWith(string ...$componentClassNames) : array;

    /**
     * Returns an array of components for the given component class name.
     * In most cases you want to use the `view` method instead.
     * 
     * @param class-string              $componentClassName
     * @return array<int, object>
     */
    public function listComponents(string $componentClassName) : array;

    /**
     * Destroyes an entity by its ID
     */
    public function destroy(int $entity) : void;

    /**
     * Attaches the given component to the given entity
     * 
     * @template T of object
     * @param int            $entity The entitiy ID of the component to be attached
     * @param T              $component
     * @return T
     */
    public function attach(int $entity, object $component) : object;

    /**
     * Dettaches a component by class its class name
     * 
     * @param int                    $entity The entitiy ID of the component to be detached
     * @param class-string           $componentClassName
     */
    public function detach(int $entity, string $componentClassName) : void;

    /**
     * Dettaches all components from the given entity
     *
     * @param int                    $entity The entitiy ID of the component to be detached
     */
    public function detachAll(int $entity) : void;

    /**
     * Returns a component for the given entity 
     * 
     * @template T
     * @param int                       $entity The entitiy ID of the component to be retrieved
     * @param class-string<T>           $componentClassName
     * 
     * @return T
     */
    public function get(int $entity, string $componentClassName);

    /**
     * Returns boolean if an entity has a component
     * 
     * @param int                    $entity The entitiy ID of the component
     * @param class-string           $componentClassName
     */
    public function has(int $entity, string $componentClassName) : bool;

    /**
     * Returns an array of all components for the given entity
     * 
     * @param int                    $entity The entitiy ID of the component
     * @return array<class-string, object>
     */
    public function components(int $entity) : array;

    /**
     * Iterates over all available components of the given class name
     * 
     * @template T
     * @param class-string<T>           $componentClassName
     * @return \Generator<int, T>
     */
    public function view(string $componentClassName) : Generator;
    
    /**
     * Returns the first component of the given class name
     * 
     * @template T
     * @param class-string<T>           $componentClassName
     * @return ?T
     */
    public function first(string $componentClassName);

    /**
     * Returns the first entity that has the given component
     * 
     * @param class-string           $componentClassName
     * @return ?int
     */
    public function firstWith(string $componentClassName) : ?int;

    /**
     * Stores a singleton component in the entity registy
     * 
     * @template T
     * @param T             $component
     */
    public function setSingleton($component) : void;

    /**
     * Returns a singleton component from the entity registry
     * 
     * @template T
     * @param class-string<T>           $componentClassName
     * @return T
     */
    public function getSingleton(string $componentClassName);

    /**
     * Returns boolean if a singleton component exists
     * 
     * @template T
     * @param class-string<T>           $componentClassName
     * @return bool
     */
    public function hasSingleton(string $componentClassName) : bool;

    /**
     * Removes a singleton component from the entity registry
     * 
     * @template T
     * @param class-string<T>           $componentClassName
     */
    public function removeSingleton(string $componentClassName) : void;
}
