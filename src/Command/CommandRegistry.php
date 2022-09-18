<?php 

namespace VISU\Command;

use ClanCats\Container\Container;

use VISU\Command\Exception\{CommandException, RegisterCommandException};

use VISU\Command\Command;

use League\CLImate\CLImate as CliInterface;

class CommandRegistry
{
    /**
     * Container instance to load the commands
     *
     * @var Container
     */
    private $container;

    /**
     * Map of command names to service names
     *
     * @var array<string, string>
     */
    protected $commands = [];

    /**
     * Construct the registry with a container
     *
     * @param Container             $container
     * @return void
     */
    public function __construct(Container $container) 
    {
        $this->container = $container;
    }

    /**
     * Register available commands in the registry
     *
     * @param Container             $container
     * @return void
     */
    public function readCommandsFromContainer(Container $container) 
    {
        foreach($container->serviceNamesWithMetaData('command') as $serviceName => $commandsMetaData)
        {
            // a command can have multiple names
            foreach($commandsMetaData as $commandMeta)
            {
                if (!is_string($commandMeta[0])) {
                    throw new RegisterCommandException('The command name needs to be a string');
                }

                $this->register($commandMeta[0], $serviceName);
            }
        }
    }

    /**
     * Available commands
     * 
     * @return array<string, string>
     */
    public function available() : array 
    {
        return $this->commands;
    }

    /**
     * Register a command with a service name
     *
     * @param string            $commandName
     * @param string            $serviceName
     * @return void
     */
    public function register(string $commandName, string $serviceName)
    {
        $this->commands[$commandName] = $serviceName;
    }

    /**
     * Does a command with the given name exist?
     *
     * @param string            $commandName
     * @return bool
     */
    public function has(string $commandName)
    {
        return isset($this->commands[$commandName]);
    }

    /**
     * Execute the command by name and with the given argument vector
     *
     * @param string            $commandName
     * @param string            $arguments
     */
    public function execute(string $commandName, string $arguments = '', ?CliInterface $cli = null, bool $overrideGlobalState = false) : void
    {
        $this->executeWithVector($commandName, explode(' ', $arguments), $cli, $overrideGlobalState);
    }

    /**
     * Loads a command by its name from the registry.
     * 
     * @param string                $commandName The command name as it is registered in the container as a meta tag.
     * @return Command Returns the command behind the given command name.
     */
    public function load(string $commandName) : Command
    {
        if (!$this->has($commandName)) {
            throw new CommandException("The command '$commandName' is not registered and cannot be executed.");
        }

        // does the container support the command 
        if (!$this->container->has($this->commands[$commandName])) {
            throw new CommandException("The command '$commandName' is registered with an unknown service.");
        }

        // load the service 
        return $this->container->get($this->commands[$commandName]);
    }

    /**
     * Execute the command by name and with the given argument vector
     *
     * @param string                   $commandName
     * @param array<mixed>             $argumentVector
     */
    public function executeWithVector(string $commandName, array $argumentVector = [], ?CliInterface $cli = null, bool $overrideGlobalState = false) : void
    {
        // a little ugly workaround but we always prepend one empty argument 
        // because the command line parser from climate will skip the first one
        // which usally contains the script name / path
        array_unshift($argumentVector, '');

        // to be used mostly when executing the commands in php instead of the shell
        if ($overrideGlobalState) {
            global $argv;
            $argv = $argumentVector;
        }
        
        // load the service 
        $command = $this->load($commandName);

        // make sure it extends the base command
        if (!$command instanceof Command) {         
            throw new CommandException("The command '$commandName' does not extend the base command \\VISU\\Command\\Command");
        }

        // create a new command line interface
        if (is_null($cli)) {
            $cli = new CliInterface;
        }

        // register the arguments and merge the default help & the defaukt verbose
        $cli->arguments->add(array_merge([
            'help' => [
                'prefix'  => 'h',
                'longPrefix'  => 'help',
                'description' => 'Tries to tell you what to do with this thing.',
                'noValue'     => true,
            ],
            'verbose' => [
                'prefix'      => 'v',
                'longPrefix'  => 'verbose',
                'description' => 'Verbose output',
                'noValue'     => true,
            ],
        ], $command->getExpectedArguments($argumentVector)));

        // set the commands description
        $cli->arguments->description($command->getCommandDescription());

        // parse the arguments based 
        $cli->arguments->parse($argumentVector);

        // just print the usage if help is requested
        if ($cli->arguments->defined('help')) {
            $cli->usage(); return;
        }

        // set the verbose state
        $command->setVerbose($cli->arguments->defined('verbose'));

        // set the command line interface
        $command->setCommandLineInterface($cli);

        // run it
        $command->execute();
    }
}
