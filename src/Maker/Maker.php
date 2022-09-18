<?php 

namespace VISU\Maker;

use VISU\Maker\Exception\InvalidCodeGeneratorException;

use League\CLImate\CLImate;

class Maker
{
    /**
     * @var array<string, GeneratorInterface>
     */
    private array $generators = [];

    /**
     * Check if a generator of type is bound
     */
    public function hasGenerator(string $generatorType) : bool
    {
        return isset($this->generators[$generatorType]);
    }

    /**
     * Binds a maker generator
     */
    public function bind(GeneratorInterface $generator) : void
    {
        $this->generators[$generator->getType()] = $generator;
    }

    /**
     * Returns an array of currently bound generator type
     * 
     * @return array<string>
     */
    public function getAvailableGeneratorTypes() : array
    {
        return array_keys($this->generators);
    }

    /**
     * Returns the corresponding generator or throws an exception
     */
    private function getGeneratorSafe(string $generatorType) : GeneratorInterface 
    {
        if (!isset($this->generators[$generatorType])) {
            $availableGenerators = implode(', ', $this->getAvailableGeneratorTypes());
            throw new InvalidCodeGeneratorException("No code generator with type (" . $generatorType . ") has been bound to the maker.\nAvailable generators are: " . $availableGenerators);
        }

        return $this->generators[$generatorType];
    }

    /**
     * Returns the expected arguments for given generator 
     * 
     * @return array<string, array<string, mixed>>
     */
    public function getExpectedArguments(string $generatorType) : array
    {
        $generator = $this->getGeneratorSafe($generatorType);
        return $generator->getExpectedArguments();
    }
    
    /**
     * Pass parameters that will be used for change generation. 
     * The generator can then validate and complete them by promting the user
     * 
     * @param array<string, mixed>          $parameters
     */
    public function evaluateParamters(string $generatorType, array &$parameters, ?CLImate $cli = null) : void 
    {
        $generator = $this->getGeneratorSafe($generatorType);
        $generator->evaluateParamters($parameters, $cli);
    }   
    
    /**
     * @param array<string, mixed>          $parameters
     * @return array<CodeChange>
     */
    public function buildChanges(string $generatorType, array $parameters) : array
    {
        $generator = $this->getGeneratorSafe($generatorType);
        return $generator->buildChanges($parameters);
    }

    /**
     * Applies the given code changes
     * 
     * @param array<CodeChange>             $changes
     * @param bool                          $force Force the changes, means override existing data if necessary
     * @param ?Climate                      $cli Command line interface to ask the user if changes shall be applied
     */
    public function applyChanges(array $changes, bool $force = false, ?CLImate $cli = null) : void
    {
        foreach($changes as $change) {
            if ($force === false) {
                if (file_exists($change->filepath)) {
                    if ($cli) {
                        $relpath = substr($change->filepath, strlen(VISU_PATH_ROOT));
                        $prefix = $change->fullOverride ? 'Override' : 'Patch';
                        $input = $cli->confirm($prefix . ' <green>' . $relpath . '</green>?');
                        if (!$input->confirmed()) {
                            continue;
                        }
                    } else {
                        continue;
                    }
                }
            }
            
            // create directories if necessary
            $path = dirname($change->filepath);
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
            
            if ($change->fullOverride) {
                file_put_contents($change->filepath, $change->code);
            } 
            // path part of the file otherwise
            else {
                $contents = '';
                if (file_exists($change->filepath)) {
                    $contents = file_get_contents($change->filepath) ?: '';
                }

                $partA = substr($contents, 0, $change->offsetStart);
                $partB = substr($contents, $change->offsetEnd);

                file_put_contents($change->filepath, $partA . $change->code . $partB);
            }
        }
    }
}
