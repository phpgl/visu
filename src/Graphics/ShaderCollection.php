<?php

namespace VISU\Graphics;

use VISU\Graphics\Exception\ShaderException;
use VISU\Graphics\Exception\ShaderProgramLinkingException;

class ShaderCollection
{   
    /**
     * Array of shader programs
     * 
     * @var array<string, ShaderProgram>
     */
    private array $shaderPrograms = [];

    /**
     * Pre registered shader files that can be loaded on demand
     * 
     * @var array<string, array<int, string>>
     */
    private array $avilableShaderFiles = [];

    /**
     * Array of global defines that will be added to all shader programs
     * 
     * @var array<string, int|float|string>
     */
    private array $globalDefines = [];

    /**
     * Array of permutation definitions for shaders
     * 
     * @var array<string, array<string, array<string, int|float|string>>>
     */
    private array $permutationDefinitions = [];

    /**
     * Array of compiled shader permutations
     * 
     * @var array<string, ShaderProgram>
     */
    private array $shaderPermutations = [];

    /**
     * Shader file loader instance
     */
    private ShaderFileLoader $shaderFileLoader;

    /**
     * Constructor
     * 
     * @param GLState $gl
     * @param string $shaderDirectory The directory where the shader files are located
     */
    public function __construct(
        private GLState $gl,
        private string $shaderDirectory, 
    )
    {
        $this->shaderFileLoader = new ShaderFileLoader($shaderDirectory);
    }

    /**
     * Enables VISU shader includes
     */
    public function enableVISUIncludes(): void
    {
        $this->shaderFileLoader->addIncludePath(VISU_PATH_FRAMEWORK_RESOURCES_SHADER . '/include/');
    }

    /**
     * Sets a global define that will be added to all shader programs
     * 
     * @param string $name
     * @param int|float|string $value
     */
    public function setGlobalDefine(string $name, $value): void
    {
        $this->globalDefines[$name] = $value;
    }

    /**
     * Defines shader permutations with different define combinations
     * 
     * @param string $shaderName The base name of the shader (without permutation suffix)
     * @param array<string, array<string, int|float|string>> $permutations Array of permutation name => defines
     * 
     * Example:
     * $shaders->definePermutations('lighting', [
     *     'basic' => ['USE_SHADOWS' => 0, 'MAX_LIGHTS' => 4],
     *     'advanced' => ['USE_SHADOWS' => 1, 'MAX_LIGHTS' => 8, 'USE_IBL' => 1]
     * ]);
     */
    public function definePermutations(string $shaderName, array $permutations): void
    {
        $this->permutationDefinitions[$shaderName] = $permutations;
    }

    /**
     * Adds a shader program to the collection
     * 
     * @param string $name A unique name / identifier for the shader program
     * @param ShaderProgram $shaderProgram
     */
    public function setShaderProgram(string $name, ShaderProgram $shaderProgram): void
    {
        if (isset($this->shaderPrograms[$name])) {
            throw new ShaderException("Shader program with name '{$name}' already exists");
        }

        $this->shaderPrograms[$name] = $shaderProgram;
    }

    /**
     * Adds a shader program from an array of shader stage files 
     * 
     * ```php
     * $shaders->registerFromFiles('myshader', [
     *   ShaderStage::VERTEX => 'myshader.vert.glsl',
     *   ShaderStage::FRAGMENT => 'myshader.frag.glsl',
     * ]);
     * 
     * @param string $name 
     * @param array<int, string> $paths An array of shader stage constants as keys and paths to the shader files as values
     * @return void 
     */
    public function registerFromFiles(string $name, array $paths) : void
    {
        if (isset($this->shaderPrograms[$name])) {
            throw new ShaderException("Shader program with name '{$name}' already exists");
        }

        $this->avilableShaderFiles[$name] = $paths;
    }

    /**
     * Returns a shader program from the collection
     * 
     * @param string $name The name of the shader program
     */
    public function get(string $name) : ShaderProgram 
    {
        if (!isset($this->shaderPrograms[$name])) 
        {
            if (!isset($this->avilableShaderFiles[$name])) {
                throw new ShaderException("Shader program with name '{$name}' does not exist and is not registered");
            }

            $shaderProgram = new ShaderProgram($this->gl);

            // attach all shader stages
            foreach($this->avilableShaderFiles[$name] as $stage => $path) {
                $shaderProgram->attach(new ShaderStage($stage, $this->shaderFileLoader->loadShader($path, $this->globalDefines)));
            }
            
            // link the shader program
            try {
                $shaderProgram->link($name);
            } catch(ShaderException $e) {
                throw new ShaderProgramLinkingException("ShaderException ('{$name}'): " . $e->getMessage(), $e->getCode(), $e);
            }

            // add the shader program to the collection
            $this->setShaderProgram($name, $shaderProgram);
        }

        return $this->shaderPrograms[$name];
    }

    /**
     * Returns a specific permutation of a shader program
     * 
     * @param string $shaderName The base name of the shader
     * @param string $permutationName The name of the permutation variant
     * @return ShaderProgram The compiled shader program with the permutation defines
     * 
     * Example: $shader = $shaders->getPermutation('lighting', 'advanced');
     */
    public function getPermutation(string $shaderName, string $permutationName): ShaderProgram
    {
        $permutationKey = $shaderName . '#' . $permutationName;
        
        if (!isset($this->shaderPermutations[$permutationKey])) {
            // check if the permutation is defined
            if (!isset($this->permutationDefinitions[$shaderName][$permutationName])) {
                throw new ShaderException("Permutation '{$permutationName}' not defined for shader '{$shaderName}'");
            }
            
            // check if the base shader files exist
            if (!isset($this->avilableShaderFiles[$shaderName])) {
                throw new ShaderException("Base shader '{$shaderName}' does not exist and is not registered");
            }
            
            $shaderProgram = new ShaderProgram($this->gl);
            $permutationDefines = $this->permutationDefinitions[$shaderName][$permutationName];
            
            // merge global defines with permutation-specific defines
            $combinedDefines = array_merge($this->globalDefines, $permutationDefines);  
            
            // attach all shader stages with the combined defines
            foreach($this->avilableShaderFiles[$shaderName] as $stage => $path) {
                $shaderProgram->attach(new ShaderStage($stage, $this->shaderFileLoader->loadShader($path, $combinedDefines)));
            }
            
            // link the shader program
            try {
                $shaderProgram->link($permutationKey);
            } catch(ShaderException $e) {
                throw new ShaderProgramLinkingException("ShaderException ('{$permutationKey}'): " . $e->getMessage(), $e->getCode(), $e);
            }
            
            $this->shaderPermutations[$permutationKey] = $shaderProgram;
        }
        
        return $this->shaderPermutations[$permutationKey];
    }

    /**
     * Gets the available permutation names for a shader
     * 
     * @param string $shaderName The base name of the shader
     * @return array<string> Array of available permutation names
     */
    public function getAvailablePermutations(string $shaderName): array
    {
        return isset($this->permutationDefinitions[$shaderName]) ? array_keys($this->permutationDefinitions[$shaderName]) : [];
    }

    /**
     * Checks if a shader has permutation variants defined
     * 
     * @param string $shaderName The base name of the shader
     * @return bool True if permutations are defined
     */
    public function hasPermutations(string $shaderName): bool
    {
        return isset($this->permutationDefinitions[$shaderName]) && !empty($this->permutationDefinitions[$shaderName]);
    }

    /**
     * Clears all cached permutations for a shader (useful for hot-reloading)
     * 
     * @param string $shaderName The base name of the shader
     */
    public function clearPermutations(string $shaderName): void
    {
        foreach ($this->shaderPermutations as $key => $shader) {
            if (str_starts_with($key, $shaderName . '#')) {
                unset($this->shaderPermutations[$key]);
            }
        }
    }

    /**
     * Scans the shader directory for shader files and adds them to the collection
     * 
     * @param string|null $directoryPath The directory to scan. If null, the shader directory will be used
     */
    public function scanShaderDirectory(?string $directoryPath = null): void
    {
        $directoryPath = $directoryPath ?: $this->shaderDirectory;
        $directoryPathLen = strlen($directoryPath);

        // recursivly scan the directory for shader files
        $programFiles = []; 

        $directoryIterator = new \RecursiveDirectoryIterator($directoryPath);
        $iterator = new \RecursiveIteratorIterator($directoryIterator);

        foreach($iterator as $file) 
        {
            // skip directories
            if ($file->isDir()) continue;

            $absolutePath = $file->getRealPath();

            if (substr($absolutePath, -10) === '.vert.glsl') {
                $shaderName = substr($absolutePath, $directoryPathLen + 1, -10);
                if (!isset($programFiles[$shaderName])) {
                    $programFiles[$shaderName] = [];
                }
                $programFiles[$shaderName][ShaderStage::VERTEX] = $absolutePath;
            }
            elseif (substr($absolutePath, -10) === '.frag.glsl') {
                $shaderName = substr($absolutePath, $directoryPathLen + 1, -10);
                if (!isset($programFiles[$shaderName])) {
                    $programFiles[$shaderName] = [];
                }
                $programFiles[$shaderName][ShaderStage::FRAGMENT] = $absolutePath;
            }
            elseif (substr($absolutePath, -10) === '.geo.glsl') {
                $shaderName = substr($absolutePath, $directoryPathLen + 1, -10);
                if (!isset($programFiles[$shaderName])) {
                    $programFiles[$shaderName] = [];
                }
                $programFiles[$shaderName][ShaderStage::GEOMETRY] = $absolutePath;
            }
            // elseif (substr($absolutePath, -10) === '.comp.glsl') {
            //     $shaderName = substr($absolutePath, $directoryPathLen + 1, -10);
            //     if (!isset($programFiles[$shaderName])) {
            //         $programFiles[$shaderName] = [];
            //     }
            //     $programFiles[$shaderName][ShaderStage::COMPUTE] = $absolutePath;
            // }
            elseif (substr($absolutePath, -11) === '.tessc.glsl') {
                $shaderName = substr($absolutePath, $directoryPathLen + 1, -11);
                if (!isset($programFiles[$shaderName])) {
                    $programFiles[$shaderName] = [];
                }
                $programFiles[$shaderName][ShaderStage::TESS_CONTROL] = $absolutePath;
            }
            elseif (substr($absolutePath, -11) === '.tesse.glsl') {
                $shaderName = substr($absolutePath, $directoryPathLen + 1, -11);
                if (!isset($programFiles[$shaderName])) {
                    $programFiles[$shaderName] = [];
                }
                $programFiles[$shaderName][ShaderStage::TESS_EVALUATION] = $absolutePath;
            }
        }
        
        // register all found shader files
        foreach($programFiles as $name => $files) {
            // on windows we replace the backslashes with forward slashes
            // so the naming is consistent across platforms
            $name = str_replace('\\', '/', $name);

            $this->registerFromFiles($name, $files);
        }
    }

    /**
     * Specifially scans the VISU shader directory for shader files and adds them to the collection
     */
    public function addVISUShaders() : void
    {
        $this->scanShaderDirectory(VISU_PATH_FRAMEWORK_RESOURCES_SHADER);
    }

    /**
     * Loads all currently registered shader programs, you can pass a callback to be called after each shader program is loaded
     * This way you can show a loading screen or something...
     * 
     * @param callable|null $callback The callback to be called after each shader program is loaded
     */
    public function loadAll(?callable $callback = null) : void
    {
        foreach($this->avilableShaderFiles as $name => $files) {
            $shader = $this->get($name);
            if ($callback) $callback($name, $shader);
        }
    }

    /**
     * Preloads all permutations for a specific shader
     * 
     * @param string $shaderName The base name of the shader
     * @param callable|null $callback Optional callback called after each permutation is loaded
     */
    public function loadAllPermutations(string $shaderName, ?callable $callback = null): void
    {
        if (!isset($this->permutationDefinitions[$shaderName])) {
            return; // no permutations defined
        }
        
        foreach ($this->permutationDefinitions[$shaderName] as $permutationName => $defines) {
            $shader = $this->getPermutation($shaderName, $permutationName);
            if ($callback) $callback($shaderName, $permutationName, $shader);
        }
    }

    /**
     * Preloads all defined permutations for all shaders
     * 
     * @param callable|null $callback Optional callback called after each permutation is loaded
     */
    public function loadAllPermutationsForAllShaders(?callable $callback = null): void
    {
        foreach ($this->permutationDefinitions as $shaderName => $permutations) {
            $this->loadAllPermutations($shaderName, $callback);
        }
    }
}
