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
            // if the component is already registered, we do nothing
            return;
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
     * Returns an array of components for the given component class name.
     * In most cases you want to use the `view` method instead.
     * 
     * @param class-string              $componentClassName
     * @return array<int, object>
     */
    public function listComponents(string $componentClassName) : array
    {
        return $this->components[$componentClassName] ?? [];
    }

    /**
     * Destroyes an entity by its ID
     */
    public function destroy(int $entity) : void
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
            throw new EntityRegistryException(sprintf("Please register your component (%s) with registry first!", $className));
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
     * Dettaches all components from the given entity
     *
     * @param int                    $entity The entitiy ID of the component to be detached
     */
    public function detachAll(int $entity) : void
    {
        foreach($this->entityComponents[$entity] as $componentClassName => $component) {
            unset($this->components[$componentClassName][$entity]);
        }

        unset($this->entityComponents[$entity]);
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
     * Returns a component for the given entity or null if it does not exist
     * 
     * @template T
     * @param int                       $entity The entitiy ID of the component to be retrieved
     * @param class-string<T>           $componentClassName
     * 
     * @return T|null
     */
    public function tryGet(int $entity, string $componentClassName)
    {
        // @phpstan-ignore-next-line
        return $this->entityComponents[$entity][$componentClassName] ?? null;
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
     * Returns an array of all components for the given entity
     * 
     * @param int                    $entity The entitiy ID of the component
     * @return array<class-string, object>
     */
    public function components(int $entity) : array
    {
        return $this->entityComponents[$entity];
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
     * Iterates over all entities having the given components and will pass the components as arguments to the callback
     * 
     * @param class-string             ...$componentClassNames
     * @return \Generator<int, array<object>>
     */
    public function viewWith(string ...$componentClassNames) : Generator
    {
        if (empty($componentClassNames)) {
            return;
        }

        $mainComponent = array_shift($componentClassNames);

        foreach ($this->view($mainComponent) as $entity => $mainComponentInstance) {
            $components = [$mainComponentInstance];
            
            foreach ($componentClassNames as $componentClassName) {
                if (!isset($this->entityComponents[$entity][$componentClassName])) {
                    continue 2; // Skip this entity if it does not have the required component
                }
                $components[] = $this->entityComponents[$entity][$componentClassName];
            }

            yield $entity => $components;
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
            return $component; // @phpstan-ignore-line
        }

        return null;
    }

    /**
     * Returns the first entity that has the given component
     * 
     * @param class-string           $componentClassName
     * @return ?int
     */
    public function firstWith(string $componentClassName) : ?int
    {
        foreach($this->components[$componentClassName] ?? [] as $entity => $component) {
            return $entity;
        }

        return null;
    }

    /**
     * Stores a singleton component in the entity registy
     * 
     * @template T
     * @param T             $component
     * @return T
     */
    public function setSingleton($component)
    {
        $this->singletonComponents[get_class($component)] = $component; 
        
        return $component;
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
     * 
     * @param array<class-string> $componentNames The component names to be serialized
     * @param ?string             $requiredComponent The component name that is required to be present in the entity to be serialized
     */
    public function serialize(array $componentNames, ?string $requiredComponent = null) : string
    {
        // make a hash map of the component names for faster lookup
        $componentNamesMap = array_flip($componentNames);

        // copy the freelist 
        $freelistCopy = $this->freelist;


        $toBeSerializedEntities = [];
        if ($requiredComponent !== null) {
            foreach($this->components[$requiredComponent] ?? [] as $entityId => $component) {
                $toBeSerializedEntities[$entityId] = true;
            }
        } else {
            foreach($this->entityComponents as $entityId => $components) {
                $toBeSerializedEntities[$entityId] = true;
            }
        }

        $serializedComponentData = [];
        foreach($this->entityComponents as $entityId => $components) {
            if (!isset($toBeSerializedEntities[$entityId])) {
                $freelistCopy[] = $entityId;
                continue;
            }

            $serializedComponentData[$entityId] = [];

            foreach($components as $componentName => $component) {
                if (!isset($componentNamesMap[$componentName])) {
                    continue;
                }

                $serializedComponentData[$entityId][$componentName] = serialize($component);
            }
        }

        // remove all serialized entities that have no components
        // and add them to the freelist
        foreach($serializedComponentData as $entityId => $components) {
            if (count($components) === 0) {
                unset($serializedComponentData[$entityId]);
                $freelistCopy[] = $entityId;
            }
        }

        // we only store the per entity component data, as we can reconstruct the entityComponents array
        // This makes ita bit easier to handle the possible component filtering. But makes serialization slower.
        // but hey its PHP soooo....
        return serialize([
            $this->entityPointer,
            $freelistCopy,
            $serializedComponentData,
        ]);
    }

    /**
     * Deserializes the registry from a string
     */
    public function deserialize(string $buffer) : void
    {
        list($this->entityPointer, $this->freelist, $componentsData) = unserialize($buffer);

        /** @var array<int, array<class-string, string>> */
        $componentsData = $componentsData ?? [];

        $componentNames = [];

        $this->entityComponents = [];
        foreach($componentsData as $entityId => $components) {
            $this->entityComponents[$entityId] = [];
            foreach($components as $componentName => $componentData) {
                $this->entityComponents[$entityId][$componentName] = unserialize($componentData);
                $componentNames[$componentName] = true;
            }
        }

        $this->components = [];
        foreach($componentNames as $componentName => $true) {
            $this->components[$componentName] = [];
        }

        foreach($this->entityComponents as $entityId => $components) {
            foreach($components as $componentName => $component) {
                $this->components[$componentName][$entityId] = $component;
            }
        }
    }
}
