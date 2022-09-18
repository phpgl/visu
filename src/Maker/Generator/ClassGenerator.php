<?php 

namespace VISU\Maker\Generator;

use VISU\Maker\{CodeChange, GeneratorBase, GeneratorInterface};
use VISU\Maker\Exception\BadParameterException;

use League\CLImate\CLImate;

class ClassGenerator extends GeneratorBase implements GeneratorInterface
{
    /**
     * Returns the generators type identifier, must be unique
     */
    public function getType() : string
    {
        return 'class';
    }

    /**
     * Pass parameters that will be used for change generation. 
     * The generator can then validate and complete them by promting the user
     * 
     * @param array<string, mixed>          $parameters
     */
    public function evaluateParamters(array &$parameters, ?CLImate $cli = null) : void
    {
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
                'description' => "The full class name including its namespace," .
                    $p . 'example:' . 
                    $p . '  App\\Servies\\Logger or App\\SignalHandler\\BootstrapHandler',
                'castTo' => 'string'
            ],
            'extends' => [
                'longPrefix' => 'extends',
                'description' => "the full class namespace of the parent class" .
                    $p . 'example:' . 
                    $p . '  VISU\\Transformer\\Transformer',
                'castTo' => 'string'
            ],
            'implements' => [
                'longPrefix' => 'implements',
                'description' => "A list of full interface namespaces that are implemented." .
                    $p . 'example:' . 
                    $p . '  Psr\\Log\\LoggerInterface',
                'castTo' => 'string'
            ],
        ];
    }

    /**
     * Build the required changes based on the given paramters
     * 
     * @param array<string, mixed>              $parameters
     * @return array<CodeChange>
     */
    public function buildChanges(array $parameters) : array
    {
        $namespace = $this->fixNamespaceString($this->ensureParamter($parameters, 'namespace'));

        $path = $this->getPathForNamespace($namespace);

        // use options form params
        $options = [];
        if (isset($parameters['extends']) && $parameters['extends']) {
            $options['extends'] = $this->fixNamespaceString($parameters['extends']);
        }

        if (isset($parameters['implements']) && $parameters['implements']) {
            $implementsArray = explode(',', $parameters['implements']);
            $implementsArray = array_map(function($v) {
                return $this->fixNamespaceString($v);
            }, $implementsArray);
            $options['implements'] = $implementsArray;
        }

        $buffer = $this->generateClassBuffer($namespace, '', $options);

        $changes = [];
        $changes[] = new CodeChange($path, $buffer);

        return $changes;
    }
}
