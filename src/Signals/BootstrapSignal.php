<?php 

namespace VISU\Signals;

use VISU\Signal\Signal;
use ClanCats\Container\Container;

class BootstrapSignal extends Signal
{
    /**
     * Container instance 
     *
     * @var Container
     */
    private ?Container $container = null;

    /**
     * Constructor
     *
     * @param Container             $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Set the container for the signal
     *
     * @param Container               $container
     * @return void
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Returns the current container or null
     *
     * @return Container|null
     */
    public function getContainer() : ?Container
    {
        return $this->container;
    }
}
