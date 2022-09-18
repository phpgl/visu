<?php 

namespace VISU\Command;

use VISU\Command\CommandRegistry;

class AvailableCommand extends Command
{
    /**
     * An instance of the command registry
     *
     * @var CommandRegistry
     */
    protected $registry;

    /**
     * The commands decsription displayed when listening commands
     * if null it will fallback to the description property
     */
    protected ?string $descriptionShort = 'Lists all available commands in VISU.';

    /**
     * An array of expected arguments 
     *
     * @var array<string, array<string, mixed>>
     */
    protected $expectedArguments = [
        'find' => [
            'description' => 'Search for a command by the given name.',
            'longPrefix' => 'find',
            'prefix' => 'f',
            'castTo' => 'string'
        ],
        'show_class' => [
            'description' => 'Displays the commands class.',
            'longPrefix' => 'show-class',
            'prefix' => 'c',
            'noValue' => true
        ],
    ];

    /**
     * Constructor
     *
     * @param CommandRegistry           $registry
     */
    public function __construct(CommandRegistry $registry) 
    {
        $this->registry = $registry;
    }

    /**
     * Command cache file path
     */
    private function getCommandsMetaCachePath() : string
    {
        return VISU_PATH_CACHE . '/AvailableCommandsMeta.php';
    }

    /**
     * has commands cache file?
     */
    private function hasCommandsMetaCache() : bool
    {
        return file_exists($this->getCommandsMetaCachePath()) && is_readable($this->getCommandsMetaCachePath());
    }

    /**
     * Loads the commands cache
     * 
     * @return array<string, array<string, mixed>>
     */
    private function loadCommandsMetaCache() : array
    {
        return require $this->getCommandsMetaCachePath();
    }

    /**
     * Write commands meta cache
     * 
     * @param array<string, array<string, mixed>>       $data
     */
    private function writeCommandsMetaCache(array $data) : void
    {
        file_put_contents($this->getCommandsMetaCachePath(), '<?php return ' . var_export($data, true) . ';');
    }

    /**
     *. Execute this command 
     */
    public function execute()
    {
        $commands = $this->registry->available();
        ksort($commands);

        $searchQuery = (string) $this->cli->arguments->get('find');
        $showClass = (bool) $this->cli->arguments->get('show_class');

        // get the current terminal width
        $twidth = (int) exec('tput cols');

        // draw VISU header
        if (!$searchQuery) {
            $this->cli->out("
     _   ____________  __
    | | / /  _/ __/ / / /
    | |/ // /_\ \/ /_/ / 
    |___/___/___/\____/    Modern OpenGL in PHP                 
");
            $this->cli->out(str_repeat('=', $twidth));
        }

        // if a search query is given filter the commands
        if ($searchQuery) {
            $commands = array_filter($commands, function($k) use($searchQuery) {
                return strpos($k, $searchQuery) !== false;
            }, ARRAY_FILTER_USE_KEY);
        }
        
        $groups = [];
        $groupCommandMaxSize = [];

        // load the commands cache
        // why? loading all commands here is super wastefull
        // and on large applications takes multiple seconds..
        $commandsCache = [];
        $commandsCacheModified = false;
        if ($this->hasCommandsMetaCache()) {
            $commandsCache = $this->loadCommandsMetaCache();
        }


        foreach($commands as $commandName => $serviceName)
        {  
            $commandNameParts = explode(':', $commandName);
            $commandGroup = reset($commandNameParts);

            if (count($commandNameParts) === 1) {
                $commandGroup = 'common';
            }

            if (!isset($groups[$commandGroup])) {
                $groups[$commandGroup] = [];
                $groupCommandMaxSize[$commandGroup] = 0;
            }

            if (!isset($commandsCache[$commandName])) {
                $commandsCache[$commandName] = [];
                $commandsCacheModified = true;

                // load the command from the registry to 
                // retrieve descriptions and co
                $command = $this->registry->load($commandName);

                $commandsCache[$commandName] = [
                    'description' => $command->getCommandDescription(),
                    'descriptionShort' => $command->getCommandShortDescription(),
                    'class' => get_class($command),
                ];
            }

            $groups[$commandGroup][] = [
                'command' => $commandName,
                'service' => $serviceName,
                'description' => $commandsCache[$commandName]['descriptionShort'],
                'class' => $commandsCache[$commandName]['class'],
            ];

            $groupCommandMaxSize[$commandGroup] = max($groupCommandMaxSize[$commandGroup], strlen($commandName));
        }

        // update the cache if reqired 
        if ($commandsCacheModified) {
            $this->writeCommandsMetaCache($commandsCache);
        }

        foreach($groups as $groupName => $commands) 
        {
            $this->cli->bold()->underline()->out($groupName);

            $groupPadding = ($groupCommandMaxSize[$groupName] + 10);
            $groupPadding = (int) (ceil($groupPadding / 10) * 10);
            $groupDescSpace = $twidth - $groupPadding;

            foreach($commands as $commandData) 
            {
                $commandPadding = $groupPadding - (strlen($commandData['command']) + 2);

                $buffer = '  <blue>' . $commandData['command'] . '</blue>' . str_repeat(' ', $commandPadding);

                $description = $commandData['description'];

                if ($showClass) {
                    $buffer .= '<yellow>' . $commandData['class'] . "</yellow>\n" . str_repeat(' ', $groupPadding);
                }

                $descriptionParts = explode("\n", wordwrap($description, $groupDescSpace));
                foreach ($descriptionParts as $descPart) 
                {
                    // append offset from word wrapping
                    $wwoff = max($groupDescSpace - strlen($descPart), 0);
                    if ($wwoff) $descPart .= str_repeat(' ', $wwoff);

                    $buffer .= $descPart . str_repeat(' ', $groupPadding);
                }

                $this->cli->out(rtrim($buffer));
            }

            $this->cli->br();
        }
    }
}
