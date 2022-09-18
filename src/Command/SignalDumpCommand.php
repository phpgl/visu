<?php 

namespace VISU\Command;

use VISU\Signal\Dispatcher;

class SignalDumpCommand extends Command
{
    /**
     * The commands decsription displayed when listening commands
     * if null it will fallback to the description property
     */
    protected ?string $descriptionShort = 'Dumps all binded signal / event listeners';

    /**
     * An instance of the signal dispatcher
     *
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * Constructor
     *
     * @param Dispatcher            $dispatcher
     */
    public function __construct(Dispatcher $dispatcher) 
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     *. Execute this command 
     */
    public function execute()
    {
        $groups = $this->dispatcher->getAllSignalHandlers(); ksort($groups);
        
        $data = [];

        foreach($groups as $signal => $handlers)
        {
            foreach ($handlers as list($priority, $handler)) 
            {
                // we currently register the signals in a closure 
                // so try to read meta data via reflection
                if (!$handler instanceof \Closure) continue;

                $info = (new \ReflectionFunction($handler))->getStaticVariables();
                
                $serviceName = $info['serviceName'] ?? '–';
                $className = '–';

                if ($info['container'] ?? false) {
                    $className = get_class($info['container']->get($serviceName));

                    // @phpstan-ignore-next-line
                    $reflect = new \ReflectionClass($className);
                    $className = $reflect->getShortName();
                }


                $data[] = 
                [
                    'signal' => "<blue>$signal</blue>",
                    'service name' => $serviceName,
                    'class' => $className,
                    'priority' => $priority,
                ];
            }
        }

        usort($data, function($item1, $item2) {
            return ($item1['signal'] . $item1['priority']) <=> ($item2['signal'] . $item1['priority']);
        });

        if (empty($data)) {
            $this->cli->error('There are no event listeners registered.');
        }
        else {
            $this->cli->table($data);
        }
    }
}
