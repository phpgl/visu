<?php 

namespace VISU\Signals\Runtime;

use VISU\Runtime\DebugConsole;
use VISU\Signal\Signal;

class ConsoleCommandSignal extends Signal
{
    /**
     * The full string that was entered
     */
    public readonly string $commandString;

    /**
     * The command parts simply split by spaces
     */
    public readonly array $commandParts;

    /**
     * An instance of the debug console that dispatched the signal
     */
    public readonly DebugConsole $console;

    /**
     * Constructor
     */
    public function __construct(
        string $commandString,
        DebugConsole $console,
    ) {
        $this->commandString = $commandString;
        $this->commandParts = explode(' ', $commandString);
        $this->console = $console;
    }

    /**
     * Returns boolean if the first command part matches the given string
     */
    public function isAction(string $command) : bool
    {
        return ($this->commandParts[0] ?? null) === $command;
    }
}
