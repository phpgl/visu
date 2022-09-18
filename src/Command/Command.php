<?php 

namespace VISU\Command;

use League\CLImate\CLImate;

abstract class Command
{
    /**
     * An array of expected arguments 
     *
     * @var array<string, array<string, mixed>>
     */
    protected $expectedArguments = [];

    /**
     * The commands decsription displayed when listening commands
     * or in the help dialog
     */
    protected string $description = '';

    /**
     * The commands decsription displayed when listening commands
     * if null it will fallback to the description property
     */
    protected ?string $descriptionShort = null;

    /**
     * Instance of the command line interface 
     *
     * @var CLImate
     */
    protected $cli = null;

    /**
     * Enable / Disable verbose
     *
     * @var bool
     */
    protected $verbose = false;

    /**
     * Sets the current command line interface
     *
     * @param CLImate           $cli
     * @return void
     */
    final public function setCommandLineInterface(CLImate $cli) 
    {
        $this->cli = $cli;
    }

    /**
     * Returns the commands description
     */
    public function getCommandDescription() : string
    {
        return $this->description ?: $this->descriptionShort ?: '';
    }

    /**
     * Returns the short commands description
     */
    public function getCommandShortDescription() : string
    {
        return $this->descriptionShort ?: $this->description;
    }

    /**
     * Returns the expected arguments and by default the class property
     *
     * @param array<mixed>              $argumentVector The unparsed argument vector, allowing for subcommands to be handled.
     * @return array<string, array<string, mixed>>
     */
    public function getExpectedArguments(array $argumentVector) : array
    {
        return $this->expectedArguments;
    }

    /**
     * Set the current verbose state
     *
     * @param bool          $value
     * @return void
     */
    public function setVerbose(bool $value)
    {
        $this->verbose = $value;
    }

    /**
     * Print a info string
     *
     * @param string                $message
     * @param bool                  $onlyVerbose
     * @param string                $key
     * @return void
     */
    public function info(string $message, bool $onlyVerbose = false, string $key = 'info')
    {
        if ($onlyVerbose && (!$this->verbose)) return;

        $this->cli->out('[<blue>' . $key . '</blue>] ' . $message);
    }

    /**
     * Print a success string
     *
     * @param string                $message
     * @param bool                  $onlyVerbose
     * @param string                $key
     * @return void
     */
    public function success(string $message, bool $onlyVerbose = false, string $key = 'success')
    {
        if ($onlyVerbose && (!$this->verbose)) return;

        $this->cli->out('[<green>' . $key . '</green>] ' . $message);
    }

    /**
     *. Execute this command 
     *
     * @return void
     */
    abstract public function execute();
}
