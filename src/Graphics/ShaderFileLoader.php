<?php

namespace VISU\Graphics;

use VISU\Graphics\Exception\ShaderException;
use VISU\Graphics\Exception\ShaderInvalidIncludeException;

class ShaderFileLoader
{   
    /**
     * An array of include paths to search for included files
     * 
     * @var array<string>
     */
    private array $additionlIncludePaths = [];

    /**
     * Constructor
     * 
     * @param string $shaderFileDir A path to a directory where the shader files are located you want
     * to be able to load.               
     */
    public function __construct(
        private string $shaderFileDir
    )
    {
        // ensure that the shader file directory ends with a slash
        if ($this->shaderFileDir[strlen($this->shaderFileDir) - 1] !== '/') {
            $this->shaderFileDir .= '/';
        }
    }

    /**
     * Returns the current shader file directory
     */
    public function getShaderFileDir() : string
    {
        return $this->shaderFileDir;
    }

    /**
     * Adds an additional include path to search for included files
     * 
     * @param string        $path Path to a directory that will be considered for includes
     * @return void 
     */
    public function addIncludePath(string $path) : void
    {
        // include paths also need to end with a slash
        if ($path[strlen($path) - 1] !== '/') {
            $path .= '/';
        }

        $this->additionlIncludePaths[] = $path;
    }

    /**
     * Generates a defines code section string
     * 
     * @param array<string, int|float|string> $defines An array of macros that will be used in the shader
     * @return string 
     */
    private function getDefinesString(array $defines) : string
    {
        $definesString = '';
        foreach ($defines as $define => $value) {
            $definesString .= '#define ' . $define . ' ' . var_export($value, true) . "\n";
        }
        return $definesString;
    }

    /**
     * Will load and process a shader file and return the final source code
     * 
     * @param string                            $shaderFile The relative path to the shader file
     * @param array<string, int|float|string>   $defines An array of macros that will be injected into the shader
     * @param bool                              $rootFile Whether or not this is the root file that is being processed, This file
     * contains the additional defines passed over $defines.
     * 
     * @return string 
     */
    public function loadShader(string $shaderFile, array $defines = [], bool $rootFile = true) : string
    {
        $shaderFilePath = $this->shaderFileDir . $shaderFile;
        if (file_exists($shaderFilePath) === false) {
            $shaderFilePath = $shaderFile;
        }

        if (file_exists($shaderFilePath) === false) {
            throw new ShaderException("Could not find shader file {$shaderFile}' in '{$this->shaderFileDir}'");
        }

        $shaderContents = file_get_contents($shaderFilePath);
        if ($shaderContents === false) {
            throw new ShaderException("Could not load shader file '{$shaderFilePath}'");
        }

        return $this->processShader($shaderContents, $defines, $rootFile);
    }

    /**
     * Will process includes and macros in the shader source code and return the final source code
     * 
     * @param string                            $shaderContents 
     * @param array<string, int|float|string>   $defines An array of macros that will be injected into the shader
     * @param bool                              $rootFile Whether or not this is the root file that is being processed, This file 
     * contains the additional defines passed over $defines.
     * 
     * @return string 
     */
    public function processShader(string $shaderContents, array $defines = [], bool $rootFile = true) : string
    {
        // add additional defines
        if ($rootFile && $defines) 
        {
            if (preg_match("/#version.*/", $shaderContents, $matches)) {
                $shaderContents = str_replace($matches[0], $matches[0] . "\n" . $this->getDefinesString($defines), $shaderContents);
            }
            else {
                throw new ShaderException('No version directive found in shader');
            }
        }

        // handle includes
        $shaderContents = $this->processShaderIncludes($shaderContents);

        return $shaderContents;
    }

    /**
     * Tries to find the shader file in the currently available include paths
     * Returns the contents of the file if found, otherwise throws an exception
     * 
     * @throws ShaderInvalidIncludeException
     * 
     * @param string $path
     * 
     * @return string The contents of the shader file
     */
    private function findIncludeFromPath(string $path) : string
    {
        $absolutePath = null;
        
        // include from base directory
        if (file_exists($this->shaderFileDir . $path)) {
            $absolutePath = $this->shaderFileDir . $path;
        }

        foreach($this->additionlIncludePaths as $includePath) {
            if (file_exists($includePath . $path)) {
                $absolutePath = $includePath . $path;
                break;
            }
        }

        if ($absolutePath === null) {
            throw new ShaderInvalidIncludeException("Could not find shader file '$path'");
        }

        if (!is_readable($absolutePath)) {
            throw new ShaderInvalidIncludeException("Shader file '$path' is not readable");
        }

        return file_get_contents($absolutePath) ?: '';
    }

    /**
     * Replaces include statements in the shader source code with the contents of the included file
     * 
     * @throws ShaderInvalidIncludeException 
     * 
     * @param string $shaderContents The shader source code
     * 
     * @return string 
     */
    public function processShaderIncludes(string $shaderContents) : string
    {
        // find and repalce includes 
        $shaderContents = preg_replace_callback('/#include +"(.*)"/', function($matches) {
                $includeFile = $matches[1];
                return $this->processShader($this->findIncludeFromPath($includeFile), [], false);
            },
            $shaderContents
        );

        if (!is_string($shaderContents)) {
            throw new ShaderInvalidIncludeException("Shader includes could not be processed");
        }

        return $shaderContents;
    }
}
