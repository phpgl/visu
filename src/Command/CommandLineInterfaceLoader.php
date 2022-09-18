<?php 

namespace VISU\Command;

use League\CLImate\CLImate as CliInterface;

class CommandLineInterfaceLoader
{
    /**
     * Command registry instance
     */
    protected CommandRegistry $commandRegistry;

    /**
     * Command line interface (climate) in this case
     */
    protected CliInterface $cli;

    /**
     * Constructor
     * 
     * @param CommandRegistry               $commandRegistry
     * @param CliInterface                  $cli
     */
    public function __construct(CommandRegistry $commandRegistry, CliInterface $cli) 
    {
        $this->commandRegistry = $commandRegistry;
        $this->cli = $cli;
    }

    /**
     * Pass to the loader.
     * 
     * @param array<mixed>                 $argumentVector
     */
    public function pass(array $argumentVector) : void
    {
        // the first argument can be ignored
        // it contains the path to the script itself
        array_shift($argumentVector);

        // if there is no command given just print whats available
        if (!isset($argumentVector[0])) {
            $this->commandRegistry->execute('commands:available'); return;
        }

        // get the command name
        $commandName = array_shift($argumentVector);

        // if the command does not exist say so
        if (!$this->commandRegistry->has($commandName)) 
        {
            // show an error
            $this->cli
                ->to('error')
                ->red('Unknown command: ' . $commandName)
                ->white('Did you mean:');

            $this->commandRegistry->execute('commands:available', '-f ' . $commandName, $this->cli); 
            return;
        }

        // execute the command
        $this->commandRegistry->executeWithVector($commandName, $argumentVector, $this->cli);
    }
}
