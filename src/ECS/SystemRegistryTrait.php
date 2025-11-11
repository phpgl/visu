<?php

namespace VISU\ECS;

use VISU\ECS\EntitiesInterface;

trait SystemRegistryTrait
{
    /**
     * Array of binded systems
     * 
     * @var array<SystemInterface>
     */
    protected array $bindedSystems = [];

    /**
     * Binds a system to the registry
     * 
     * Will check if the system is already binded before adding it.
     */
    public function bindSystem(SystemInterface $system) : void
    {
        // check if the system is not already binded
        if (in_array($system, $this->bindedSystems, true)) {
            return;
        }

        $this->bindedSystems[] = $system;
    }

    /**
     * Binds multiple systems to the registry
     * 
     * @param array<SystemInterface> $systems
     */
    public function bindSystems(array $systems) : void
    {
        foreach ($systems as $system) {
            $this->bindSystem($system);
        }
    }

    /**
     * Registers all binded systems to the entity registry
     */
    public function registerSystems(EntitiesInterface $entities) : void
    {
        foreach ($this->bindedSystems as $system) {
            $system->register($entities);
        }
    }

    /**
     * Unregisters all binded systems from the entity registry
     */
    public function unregisterSystems(EntitiesInterface $entities) : void
    {
        foreach ($this->bindedSystems as $system) {
            $system->unregister($entities);
        }
    }
}
