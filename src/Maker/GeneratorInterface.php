<?php 

namespace VISU\Maker;

use League\CLImate\CLImate;

interface GeneratorInterface
{
    /**
     * Returns the generators type identifier, must be unique
     */
    public function getType() : string;

    /**
     * Returns expected arguments for the code generator
     * 
     * @return array<string, array<string, mixed>>
     */
    public function getExpectedArguments() : array;

    /**
     * Pass parameters that will be used for change generation. 
     * The generator can then validate and complete them by promting the user
     * 
     * @param array<string, mixed>          $parameters
     */
    public function evaluateParamters(array &$parameters, ?CLImate $cli = null) : void;

    /**
     * Build the required changes based on the given paramters
     * 
     * @param array<string, mixed>              $parameters
     * @return array<CodeChange>
     */
    public function buildChanges(array $parameters) : array;
}
