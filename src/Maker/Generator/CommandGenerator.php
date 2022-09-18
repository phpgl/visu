<?php 

namespace VISU\Maker\Generator;

use VISU\Maker\{CodeChange, GeneratorBase, GeneratorInterface};
use VISU\Maker\Exception\BadParameterException;

use League\CLImate\CLImate;

class CommandGenerator extends GeneratorBase implements GeneratorInterface
{
    private string $commandsCtnPath = 'commands.ctn';

    private string $commandNamespace = 'App\\Command';

    /**
     * Returns the generators type identifier, must be unique
     */
    public function getType() : string
    {
        return 'command';
    }

    /**
     * Returns expected arguments for the code generator
     * 
     * @return array<string, array<string, mixed>>
     */
    public function getExpectedArguments() : array
    {
        $p = PHP_EOL . str_repeat(' ', 16);

        return [
            'namespace' => [
                'description' => "The commands name and path" .
                    $p . 'example:' . 
                    $p . '  RebuildStatsCacheCommand or DB/BackupCommand',
                'castTo' => 'string'
            ],
            'alias' => [
                'longPrefix' => 'alias',
                'description' => "The alias over which the command should be invoked" .
                    $p . 'example:' . 
                    $p . '  "stats:rebuild"' .
                    $p . '  "db:backup"',
                'castTo' => 'string'
            ],
            'extends' => [
                'longPrefix' => 'extends',
                'description' => "the full class namespace of the parent command class" .
                    $p . 'example:' . 
                    $p . '  VISU\Command\Command',
                'castTo' => 'string'
            ],
        ];
    }

    /**
     * Pass parameters that will be used for change generation. 
     * The generator can then validate and complete them by promting the user
     * 
     * @param array<string, mixed>          $parameters
     */
    public function evaluateParamters(array &$parameters, ?CLImate $cli = null) : void
    {
        $this->ensureParamter($parameters, 'namespace');
    }

    public function setPathCommandsCtn(string $commandsCtnPath) : void
    {
        $this->commandsCtnPath = $commandsCtnPath;
    }

    public function setCommandNamespace(string $namespace) : void
    {
        $this->commandNamespace = $namespace;
    }

    /**
     * Build the required changes based on the given paramters
     * 
     * @param array<string, mixed>              $parameters
     * @return array<CodeChange>
     */
    public function buildChanges(array $parameters) : array
    {
        $baseNamespace = $this->fixNamespaceString($this->commandNamespace);
        $commandName = $this->fixNamespaceString($this->ensureParamter($parameters, 'namespace'));
        
        if (substr($commandName, -6) === 'Command') {
            $commandName = substr($commandName, 0, -6);
        }

        $namespace = $this->fixNamespaceString($baseNamespace . "\\" . $commandName . 'Command');

        $path = $this->getPathForNamespace($namespace);

        $extendsClass = $this->fixNamespaceString($parameters['extends'] ?: \VISU\Command\Command::class);

        $options = [
            'extends' => $extendsClass,
            'use' => [
            ]
        ];

        $handlerFuncBuffer = <<<EOT
/**
 * The commands decsription displayed when listening commands
 * if null it will fallback to the description property
 */
protected ?string \$descriptionShort = null;

/**
 * The full command description, displayed on the commands help page
 */
protected string \$description = '';

/**
 * An array of expected arguments 
 *
 * @var array<string, array<string, mixed>>
 */
protected \$expectedArguments = [
];


EOT;

        $handlerFuncBuffer .= "/**\n * The commands entry point\n * \n * @return void\n */\n";
        $handlerFuncBuffer .= "public function execute()\n{\n    return \$this->cli->out('do something!');\n}";

        $buffer = $this->generateClassBuffer($namespace, $handlerFuncBuffer, $options);

        $changes = [];
        $changes[] = new CodeChange($path, $buffer);

        // if a route is specified also generate a container binding
        if (isset($parameters['alias']) && $parameters['alias']) 
        {
            $alias = $parameters['alias'];

            $serviceName = $this->convertNamespaceToServiceName($commandName, 'command');

            $routesBuffer = $serviceName . ': ' . $namespace;
            $routesBuffer .= PHP_EOL . sprintf('  = command: \'%s\'', $alias);

            // also generate a routes.ctn entry if required
            $ctnChange = new CodeChange(VISU_PATH_APPCONFIG . '/' . $this->commandsCtnPath, $routesBuffer);
            $this->appendToCtn($ctnChange);
            $changes[] = $ctnChange;

            // check if the alias is already bound...
            if ($this->pregMatchInFile($ctnChange->filepath, "/= command: +['|\"]" . $alias . "['|\"]/")) {
                throw $this->createBadParamterException(sprintf('There is already a command "%s" bound in the file: %s', $alias, $this->commandsCtnPath));
            }
        }

        return $changes;
    }
}
