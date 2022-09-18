<?php 

namespace VISU\Command;

use ClanCats\Container\Container;

class ContainerDumpCommand extends Command
{
    /**
     * The commands decsription displayed when listening commands
     * if null it will fallback to the description property
     */
    protected ?string $descriptionShort = 'Dumps all in the applications registered services.';

    /**
     * An instance of the router
     *
     * @var Container
     */
    protected $container;

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
     *. Execute this command 
     */
    public function execute()
    {
        $services = $this->container->available(); sort($services);

        $data = [];

        foreach($services as $serviceName)
        {
            $data[] = 
            [
                'service' => "<blue>$serviceName</blue>",
                'class' => get_class($this->container->get($serviceName))
            ];
        }

        usort($data, function($item1, $item2) {
            return $item1['service'] <=> $item2['service'];
        });

        $this->cli->table($data);
    }
}
