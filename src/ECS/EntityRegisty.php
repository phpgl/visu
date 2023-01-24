<?php

namespace VISU\ECS;

use Generator;
use VISU\ECS\Exception\EntityRegistryException;

class EntityRegisty implements EntitiesInterface
{
    /**
     * An array of entity ids that have been destroyed and can be used again.
     * 
     * @var array<int>
     */
    private array $freelist = [];

    /**
     * The current entity pointer aka the next entity ID
     */
    private int $entityPointer = 0;

    /**
     * An array holding the actual components
     * 
     * @var array<class-string, array<int, object>>
     */
    private array $components = [];

    /**
     * An array singleton components
     * 
     * @var array<class-string, object>
     */
    private array $singletonComponents = [];

    /**
     * Similar to $components but holds the component references per entity
     * 
     * @var array<int, array<class-string, object>>
     */
    private array $entityComponents = [];

    /**
     * Creates an entity and returns its ID
     */
    public function create() : int
    {
        if (!empty($this->freelist)) {
            return array_pop($this->freelist);
        }

        $this->entityPointer++;
        $this->entityComponents[$this->entityPointer] = [];

        return $this->entityPointer;
    }

    /**
     * Returns boolean if the given entity ID is a valid one
     */
    public function valid(int $entity) : bool
    {
        return isset($this->entityComponents[$entity]);
    }

    /**
     * Prepares internal data structures for the given component
     * 
     * @param class-string              $componentClassName
     */
    public function registerComponent(string $componentClassName) : void
    {
        if (isset($this->components[$componentClassName])) {
            throw new EntityRegistryException(sprintf("The component '%s' is already registered.", $componentClassName));
        }

        $this->components[$componentClassName] = [];
    }

    /**
     * @param class-string              $componentClassName
     * @return array<int>
     */
    public function list(string $componentClassName) : array
    {
        return array_keys($this->components[$componentClassName] ?? []);
    }

    /**
     * @param class-string             $componentClassNames
     * @return array<int>
     */
    public function listWith(string ...$componentClassNames) : array
    {
        $entities = [];

        if (empty($componentClassNames)) {
            return [];
        }

        $mainComponent = array_shift($componentClassNames);

        foreach($this->view($mainComponent) as $entity => $comp) {
            foreach($componentClassNames as $compName) {
                if (!isset($this->entityComponents[$entity][$compName])) {
                    continue 2;
                }
            }

            $entities[] = $entity;
        }
        
        return $entities;
    }

    /**
     * Destroyes an entity by its ID
     */
    public function destory(int $entity) : void
    {
        $componentClasses = array_keys($this->entityComponents[$entity]);
        unset($this->entityComponents[$entity]);
        foreach($componentClasses as $componentClass) {
            unset($this->components[$componentClass][$entity]);
        }

        $this->freelist[] = $entity;
    } 

    /**
     * Attaches the given component to the given entity
     * 
     * @template T of object
     * @param int            $entity The entitiy ID of the component to be attached
     * @param T              $component
     * @return T
     */
    public function attach(int $entity, object $component) : object
    {
        $className = get_class($component);
        if (!isset($this->components[$className])) {
            throw new EntityRegistryException(sprintf("Pleaese register your component (%s) with registry first!", $className));
        }

        $this->components[$className][$entity] = $component;
        $this->entityComponents[$entity][$className] = $component;

        return $component;
    }

    /**
     * Dettaches a component by class its class name
     * 
     * @param int                    $entity The entitiy ID of the component to be detached
     * @param class-string           $componentClassName
     */
    public function detach(int $entity, string $componentClassName) : void
    {
        unset(
            $this->components[$componentClassName][$entity],
            $this->entityComponents[$entity][$componentClassName]
        );
    }

    /**
     * Returns a component for the given entity 
     * ! Warning: This method does no error checking and assumes you made sure the component needs to actually exist!
     * 
     * @template T
     * @param int                       $entity The entitiy ID of the component to be retrieved
     * @param class-string<T>           $componentClassName
     * 
     * @return T
     */
    public function get(int $entity, string $componentClassName)
    {
        // @phpstan-ignore-next-line
        return $this->entityComponents[$entity][$componentClassName];
    }

    /**
     * Returns boolean if an entity has a component
     * 
     * @param int                    $entity The entitiy ID of the component
     * @param class-string           $componentClassName
     */
    public function has(int $entity, string $componentClassName) : bool
    {
        return isset($this->entityComponents[$entity][$componentClassName]);
    }

    /**
     * Iterates over all available components of the given class name
     * 
     * @template T
     * @param class-string<T>           $componentClassName
     * @return Generator<int, T>
     */
    public function view(string $componentClassName) : Generator
    {
        foreach(($this->components[$componentClassName] ?? []) as $entity => $component) {
            // @phpstan-ignore-next-line
            yield $entity => $component;
        }
    }

    /**
     * Returns the first component of the given class name
     * 
     * @template T
     * @param class-string<T>           $componentClassName
     * @return ?T
     */
    public function first(string $componentClassName)
    {
        foreach($this->components[$componentClassName] ?? [] as $entity => $component) {
            return $component;
        }
    }

    /**
     * Stores a singleton component in the entity registy
     * 
     * @template T of object
     * @param T             $component
     */
    public function setSingleton($component) : void
    {
        $this->singletonComponents[get_class($component)] = $component; 
    }

    /**
     * Returns a singleton component from the entity registry
     * 
     * @template T of object
     * @param class-string<T>           $componentClassName
     * @return T
     */
    public function getSingleton(string $componentClassName)
    {
        if (!isset($this->singletonComponents[$componentClassName])) {
            throw new EntityRegistryException(sprintf("The singleton component '%s' does not exist.", $componentClassName));
        }

        return $this->singletonComponents[$componentClassName]; // @phpstan-ignore-line im don't get the error here
    }

    /**
     * Returns boolean if a singleton component exists
     * 
     * @template T of object
     * @param class-string<T>           $componentClassName
     * @return bool
     */
    public function hasSingleton(string $componentClassName) : bool
    {
        return isset($this->singletonComponents[$componentClassName]);
    }

    /**
     * Removes a singleton component from the entity registry
     * 
     * @template T of object
     * @param class-string<T>           $componentClassName
     */
    public function removeSingleton(string $componentClassName) : void
    {
        unset($this->singletonComponents[$componentClassName]);
    }

    /**
     * Serializes the registry to a string
     */
    public function serialize() : string
    {
        $buffer = '';

        // there is no need to store the full entityComponents array as it 
        // is just an additinal reference map for fast lookups and can be 
        // rebuild with just the entityIds and the components data...
        $buffer .= serialize([$this->freelist, $this->entityPointer, $this->components, array_keys($this->entityComponents)]);

        if (!$buffer = gzencode($buffer, 9)) {
            throw new EntityRegistryException('Could not serialize and compress registry...');
        }

        return $buffer;
    }

    /**
     * Deserializes the registry from a string
     */
    public function deserialize(string $buffer) : void
    {
        if (!$buffer = gzdecode($buffer)) {
            throw new EntityRegistryException('Could not decode registry state :(');
        }

        list($this->freelist, $this->entityPointer, $this->components, $validEntities) = unserialize($buffer);

        // rebuild entity components
        $this->entityComponents = array_combine($validEntities, array_fill(0, count($validEntities), []));

        foreach($this->components as $classString => $entityObjectTuples) {
            foreach($entityObjectTuples as $entityId => $component) {
                $this->entityComponents[$entityId][$classString] = $component; // @phpstan-ignore-line
            }
        }
    }
}
